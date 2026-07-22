<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CouponService;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CouponWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_percentage_fixed_and_case_insensitive_coupons(): void
    {
        $percentage = $this->coupon(['code' => 'SAVE10', 'type' => 'percentage', 'value' => '10.00']);
        $fixed = $this->coupon(['code' => 'RM50', 'type' => 'fixed_amount', 'value' => '50.00']);
        $fixedCappedAtSubtotal = $this->coupon(['code' => 'BIGFIXED', 'type' => 'fixed_amount', 'value' => '500.00']);
        $service = app(CouponService::class);

        $percentageResult = $service->calculate('save10', 35000, 800);
        $fixedResult = $service->calculate('rm50', 35000, 800);

        $this->assertSame($percentage->id, $percentageResult['coupon']->id);
        $this->assertSame('SAVE10', $percentageResult['coupon_code']);
        $this->assertSame(3500, $percentageResult['discount_cents']);
        $this->assertSame(32300, $percentageResult['total_cents']);
        $this->assertSame(5000, $fixedResult['discount_cents']);
        $this->assertSame(10000, $service->calculate($fixedCappedAtSubtotal->code, 10000, 0)['discount_cents']);
    }

    public function test_minimum_amount_maximum_discount_inactive_expired_and_future_rules(): void
    {
        $service = app(CouponService::class);
        $minimum = $this->coupon(['minimum_order_amount' => '200.00']);
        $maximum = $this->coupon(['code' => 'CAP', 'type' => 'percentage', 'value' => '50.00', 'maximum_discount_amount' => '30.00']);

        $this->assertCouponRejected(fn () => $service->calculate($minimum->code, 19999, 0));
        $this->assertSame(3000, $service->calculate('cap', 10000, 0)['discount_cents']);

        foreach ([
            ['code' => 'OFF', 'is_active' => false],
            ['code' => 'OLD', 'expires_at' => now()->subMinute()],
            ['code' => 'LATER', 'starts_at' => now()->addMinute()],
        ] as $attributes) {
            $coupon = $this->coupon($attributes);
            $this->assertCouponRejected(fn () => $service->calculate($coupon->code, 50000, 0));
        }
    }

    public function test_usage_and_per_email_limits_use_usage_records_not_only_cached_count(): void
    {
        $coupon = $this->coupon(['usage_limit' => 1, 'usage_limit_per_email' => 1, 'used_count' => 0]);
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $this->order()->id,
            'customer_email' => 'guest@example.test',
            'discount_amount' => '10.00',
            'used_at' => now(),
        ]);

        $service = app(CouponService::class);
        $this->assertCouponRejected(fn () => $service->calculate($coupon->code, 10000, 0, 'another@example.test'));

        $perEmail = $this->coupon(['code' => 'EMAILONLY', 'usage_limit_per_email' => 1]);
        CouponUsage::create([
            'coupon_id' => $perEmail->id,
            'order_id' => $this->order()->id,
            'customer_email' => 'Guest@Example.Test',
            'discount_amount' => '10.00',
            'used_at' => now(),
        ]);
        $this->assertCouponRejected(fn () => $service->calculate($perEmail->code, 10000, 0, 'guest@example.test'));
    }

    public function test_free_shipping_coupon_and_browser_discount_manipulation_are_ignored_for_duitnow(): void
    {
        Notification::fake();
        $coupon = $this->coupon(['code' => 'FREESHIP', 'free_shipping' => true]);
        $product = $this->product(['price' => '100.00', 'stock' => 3]);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '8.00']);

        $this->withSession(['cart' => [$product->id => 2], 'coupon_code' => strtolower($coupon->code)])
            ->post(route('checkout.store'), array_merge($this->checkoutData($method), [
                'discount_amount' => 999999,
                'shipping_fee' => 0,
                'total' => 0,
            ]))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('20.00', $order->discount_amount);
        $this->assertSame('8.00', $order->shipping_fee);
        $this->assertSame('8.00', $order->free_shipping_discount);
        $this->assertSame('180.00', $order->total);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id, 'order_id' => $order->id]);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_stripe_checkout_total_uses_server_discount_and_shipping_once(): void
    {
        Notification::fake();
        $coupon = $this->coupon(['code' => 'TENOFF', 'type' => 'percentage', 'value' => '10.00']);
        $product = $this->product(['price' => '100.00', 'stock' => 4]);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '10.00']);

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('beginCheckout')->once()->with(Mockery::on(function (Order $order): bool {
            $this->assertSame('200.00', $order->subtotal);
            $this->assertSame('20.00', $order->discount_amount);
            $this->assertSame('10.00', $order->shipping_fee);
            $this->assertSame('190.00', $order->total);

            return true;
        }))->andReturnUsing(function (Order $order): object {
            $order->update(['stripe_checkout_session_id' => 'cs_coupon', 'stripe_payment_status' => 'unpaid']);

            return (object) ['id' => 'cs_coupon', 'url' => 'https://checkout.stripe.test/cs_coupon'];
        });
        $this->app->instance(StripeCheckoutService::class, $stripe);

        $this->withSession(['cart' => [$product->id => 2], 'coupon_code' => $coupon->code])
            ->post(route('checkout.store'), array_merge($this->checkoutData($method), ['payment_method' => 'stripe', 'total' => 1]))
            ->assertRedirect('https://checkout.stripe.test/cs_coupon');

        $order = Order::query()->firstOrFail();
        $this->assertSame('190.00', $order->total);
        $this->assertDatabaseCount('coupon_usages', 1);
    }

    public function test_stripe_payload_matches_discounted_order_total(): void
    {
        $order = $this->order([
            'coupon_code' => 'SAVE10',
            'subtotal' => '200.00',
            'discount_amount' => '20.00',
            'shipping_fee' => '10.00',
            'original_shipping_fee' => '10.00',
            'free_shipping_discount' => '0.00',
            'total' => '190.00',
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
        ]);
        $order->items()->create(['name' => 'Coupon Dress', 'product_name' => 'Coupon Dress', 'quantity' => 2, 'unit_price' => '100.00', 'total' => '200.00', 'line_total' => '200.00']);

        $payload = app(StripeCheckoutService::class)->checkoutPayload($order);

        $this->assertSame(19000, collect($payload['line_items'])->sum(fn (array $line) => $line['price_data']['unit_amount'] * $line['quantity']));
        $this->assertSame(18000, $payload['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame(1000, $payload['line_items'][1]['price_data']['unit_amount']);
    }

    public function test_coupon_can_be_applied_and_removed_from_guest_cart(): void
    {
        $coupon = $this->coupon(['code' => 'APPLYME', 'expires_at' => now()->addDay()]);
        $product = $this->product(['price' => '100.00']);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('cart.coupon.apply'), ['coupon_code' => '  '.strtolower($coupon->code).'  '])
            ->assertSessionHas('coupon_code', 'APPLYME')
            ->assertSessionHas('success', 'Coupon APPLYME applied successfully.');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('APPLYME')
            ->assertSee('Discount');

        $this->delete(route('cart.coupon.remove'))->assertSessionMissing('coupon_code');
    }

    public function test_welcome_cherries_coupon_is_normalized_displayed_and_persists_from_cart_to_checkout(): void
    {
        $coupon = $this->welcomeCoupon();
        $product = $this->product(['price' => '350.00']);
        $method = $this->deliveryMethod();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('cart.coupon.apply'), ['coupon_code' => '  welcomecherries10  '])
            ->assertRedirect()
            ->assertSessionHas('coupon_code', 'WELCOMECHERRIES10')
            ->assertSessionHas('success', 'Coupon WELCOMECHERRIES10 applied successfully.');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee($coupon->code)
            ->assertSee('Discount')
            ->assertSee('RM 10.00');

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee($coupon->code)
            ->assertSee('Discount')
            ->assertSee('RM 340.00');
    }

    public function test_invalid_and_inactive_coupon_application_show_clear_errors(): void
    {
        $product = $this->product();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('cart.coupon.apply'), ['coupon_code' => 'not-a-real-coupon'])
            ->assertSessionHasErrors(['coupon' => 'Coupon does not exist.']);

        $inactive = $this->coupon(['code' => 'INACTIVE', 'is_active' => false]);
        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('cart.coupon.apply'), ['coupon_code' => $inactive->code])
            ->assertSessionHasErrors(['coupon' => 'Coupon is inactive.']);
    }

    public function test_welcome_cherries_discount_is_revalidated_for_duitnow_checkout(): void
    {
        Notification::fake();
        $coupon = $this->welcomeCoupon();
        $product = $this->product(['price' => '350.00', 'stock' => 2]);
        $method = $this->deliveryMethod(['is_pickup' => true]);

        $this->withSession(['cart' => [$product->id => 1], 'coupon_code' => strtolower($coupon->code)])
            ->post(route('checkout.store'), array_merge($this->checkoutData($method), [
                'discount_amount' => 999999,
                'total' => 0,
            ]))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('WELCOMECHERRIES10', $order->coupon_code);
        $this->assertSame('10.00', $order->discount_amount);
        $this->assertSame('340.00', $order->total);
    }

    public function test_welcome_cherries_discount_is_included_in_stripe_total(): void
    {
        Notification::fake();
        $coupon = $this->welcomeCoupon();
        $product = $this->product(['price' => '350.00', 'stock' => 2]);
        $method = $this->deliveryMethod(['is_pickup' => true]);

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('beginCheckout')->once()->with(Mockery::on(function (Order $order): bool {
            $this->assertSame('10.00', $order->discount_amount);
            $this->assertSame('340.00', $order->total);

            return true;
        }))->andReturn((object) ['id' => 'cs_welcome', 'url' => 'https://checkout.stripe.test/cs_welcome']);
        $this->app->instance(StripeCheckoutService::class, $stripe);

        $this->withSession(['cart' => [$product->id => 1], 'coupon_code' => $coupon->code])
            ->post(route('checkout.store'), array_merge($this->checkoutData($method), ['payment_method' => 'stripe']))
            ->assertRedirect('https://checkout.stripe.test/cs_welcome');

        $this->assertSame('340.00', Order::query()->firstOrFail()->total);
    }

    public function test_coupon_application_returns_a_clear_message_when_the_coupon_is_not_yet_active(): void
    {
        $coupon = $this->coupon(['code' => 'SCHEDULED', 'starts_at' => now()->addHour()]);
        $product = $this->product();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('cart.coupon.apply'), ['coupon_code' => $coupon->code])
            ->assertSessionHasErrors(['coupon' => 'Coupon is not active yet.'])
            ->assertSessionMissing('coupon_code');
    }

    public function test_coupon_usage_is_recorded_only_once_for_an_order(): void
    {
        $coupon = $this->coupon();
        $order = $this->order();
        $service = app(CouponService::class);

        $service->recordUsage($coupon, $order, 'coupon@example.test', 1000);
        $service->recordUsage($coupon, $order, 'coupon@example.test', 1000);

        $this->assertDatabaseCount('coupon_usages', 1);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_non_admin_cannot_manage_coupons(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.coupons.index'))->assertForbidden();
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE'.Str::upper(Str::random(8)),
            'name' => 'Seasonal saving',
            'type' => 'percentage',
            'value' => '10.00',
            'is_active' => true,
            'free_shipping' => false,
        ], $attributes));
    }

    private function welcomeCoupon(): Coupon
    {
        return Coupon::create([
            'code' => 'WELCOMECHERRIES10',
            'name' => 'Welcome to Cherry Bellemont',
            'type' => 'fixed_amount',
            'value' => '10.00',
            'minimum_order_amount' => '100.00',
            'usage_limit_per_email' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'free_shipping' => false,
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Coupon Piece '.Str::random(6), 'description' => 'A considered piece.', 'price' => '100.00', 'status' => 'active', 'stock' => 10], $attributes));
    }

    private function shippingZone(array $attributes = []): ShippingZone
    {
        return ShippingZone::create(array_merge(['name' => 'Coupon Zone '.Str::random(6), 'state' => 'Selangor', 'city_or_area' => 'Ampang', 'base_fee' => '8.00', 'is_active' => true, 'sort_order' => 0], $attributes));
    }

    private function deliveryMethod(array $attributes = []): DeliveryMethod
    {
        return DeliveryMethod::create(array_merge(['name' => 'Standard Delivery', 'code' => 'coupon-'.Str::lower(Str::random(8)), 'additional_fee' => '0.00', 'is_pickup' => false, 'is_active' => true, 'sort_order' => 0], $attributes));
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-COUPON-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64),
            'customer_name' => 'Coupon Guest', 'customer_email' => 'coupon@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Test Street'], 'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00', 'discount_amount' => '0.00', 'shipping_fee' => '0.00', 'free_shipping_discount' => '0.00', 'total' => '100.00',
            'payment_method' => 'duitnow', 'payment_provider' => 'duitnow', 'payment_status' => 'pending', 'order_status' => 'pending', 'status' => 'pending',
        ], $attributes));
    }

    private function checkoutData(DeliveryMethod $method): array
    {
        return [
            'customer_name' => 'Coupon Guest', 'customer_email' => 'coupon@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'delivery_method_id' => $method->id, 'payment_method' => 'duitnow',
        ];
    }

    private function assertCouponRejected(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected coupon validation to fail.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
