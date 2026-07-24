<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Notifications\OrderCustomerNotification;
use App\Services\OrderDocumentService;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class GiftWrappingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_gift_experience_adds_exactly_rm30_and_ignores_browser_fee_values(): void
    {
        Notification::fake();
        $product = $this->product(['price' => '100.00', 'stock' => 3]);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '10.00']);

        $this->withSession(['cart' => [$product->id => 1]])->post(route('checkout.store'), $this->checkoutData($method) + [
            'gift_wrapping' => '1',
            'gift_message' => 'Happy birthday, with love.',
            'gift_wrapping_fee' => '0.01',
            'total' => '0.01',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertTrue($order->gift_wrapping);
        $this->assertSame('30.00', $order->gift_wrapping_fee);
        $this->assertSame('Happy birthday, with love.', $order->gift_message);
        $this->assertSame('140.00', $order->total);
    }

    public function test_unchecked_signature_gift_experience_adds_no_fee(): void
    {
        Notification::fake();
        $product = $this->product(['price' => '100.00']);
        $pickup = $this->deliveryMethod(['is_pickup' => true]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->checkoutData($pickup))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertFalse($order->gift_wrapping);
        $this->assertSame('0.00', $order->gift_wrapping_fee);
        $this->assertNull($order->gift_message);
        $this->assertSame('100.00', $order->total);
    }

    public function test_coupon_shipping_and_gift_wrapping_totals_are_shared_by_duitnow(): void
    {
        Notification::fake();
        $product = $this->product(['price' => '350.00']);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '8.00']);
        Coupon::create([
            'code' => 'WELCOMECHERRIES10',
            'name' => 'RM10 Welcome',
            'type' => 'fixed_amount',
            'value' => '10.00',
            'is_active' => true,
        ]);

        $this->withSession(['cart' => [$product->id => 1], 'coupon_code' => 'WELCOMECHERRIES10'])
            ->post(route('checkout.store'), $this->checkoutData($method) + ['gift_wrapping' => '1'])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('10.00', $order->discount_amount);
        $this->assertSame('8.00', $order->shipping_fee);
        $this->assertSame('30.00', $order->gift_wrapping_fee);
        $this->assertSame('378.00', $order->total);
        $this->get(route('orders.guest.duitnow', ['order' => $order->order_number, 'token' => $order->guest_access_token]))
            ->assertOk()
            ->assertSee('Signature Gift Experience')
            ->assertSee('378.00');
    }

    public function test_shipping_quote_returns_the_server_calculated_gift_fee(): void
    {
        $product = $this->product(['price' => '100.00']);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '8.00']);

        $this->withSession(['cart' => [$product->id => 1]])
            ->postJson(route('shipping.quote'), [
                'state' => 'Selangor',
                'city' => 'Ampang',
                'postcode' => '68000',
                'delivery_method_id' => $method->id,
                'gift_wrapping' => true,
            ])
            ->assertOk()
            ->assertJsonPath('gift_wrapping_fee', '30.00')
            ->assertJsonPath('total', '138.00');
    }

    public function test_stripe_payload_charges_the_signature_gift_experience_once(): void
    {
        $order = $this->order([
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'subtotal' => '100.00',
            'shipping_fee' => '10.00',
            'gift_wrapping' => true,
            'gift_wrapping_fee' => '30.00',
            'total' => '140.00',
        ]);
        $order->items()->create([
            'name' => 'Giftable Piece',
            'product_name' => 'Giftable Piece',
            'quantity' => 1,
            'unit_price' => '100.00',
            'line_total' => '100.00',
            'total' => '100.00',
        ]);

        $payload = app(StripeCheckoutService::class)->checkoutPayload($order);
        $giftLines = collect($payload['line_items'])->filter(fn (array $line): bool => $line['price_data']['product_data']['name'] === 'Cherry Bellemont Signature Gift Experience');

        $this->assertCount(1, $giftLines);
        $this->assertSame(3000, $giftLines->first()['price_data']['unit_amount']);
        $this->assertSame(14000, collect($payload['line_items'])->sum(fn (array $line): int => $line['price_data']['unit_amount'] * $line['quantity']));
    }

    public function test_customer_admin_documents_and_transactional_email_show_gift_details(): void
    {
        $product = $this->product();
        $order = $this->order([
            'payment_status' => 'paid',
            'subtotal' => '100.00',
            'shipping_fee' => '8.00',
            'gift_wrapping' => true,
            'gift_wrapping_fee' => '30.00',
            'gift_message' => 'Congratulations on your new beginning.',
            'total' => '138.00',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '100.00',
            'line_total' => '100.00',
            'total' => '100.00',
        ]);

        $documentData = app(OrderDocumentService::class)->documentData($order->fresh());
        $this->assertStringContainsString('Signature Gift Experience', view('pdf.invoice', $documentData)->render());
        $packingSlip = view('pdf.packing-slip', $documentData)->render();
        $this->assertStringContainsString('GIFT ORDER', $packingSlip);
        $this->assertStringContainsString('Congratulations on your new beginning.', $packingSlip);

        $this->get(route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]))
            ->assertOk()
            ->assertSee('Signature Gift Experience')
            ->assertSee('Congratulations on your new beginning.');
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Signature Gift Experience')
            ->assertSee('Congratulations on your new beginning.');

        $mail = (new OrderCustomerNotification($order, 'payment_approved'))->toMail(new AnonymousNotifiable());
        $this->assertStringContainsString('Signature Gift Experience', view($mail->view['html'], $mail->viewData)->render());
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Gift Piece '.Str::random(6),
            'description' => 'A considered piece.',
            'price' => '100.00',
            'status' => 'active',
            'stock' => 10,
        ], $attributes));
    }

    private function shippingZone(array $attributes = []): ShippingZone
    {
        return ShippingZone::create(array_merge([
            'name' => 'Gift Zone '.Str::random(6),
            'state' => 'Selangor',
            'city_or_area' => 'Ampang',
            'base_fee' => '8.00',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function deliveryMethod(array $attributes = []): DeliveryMethod
    {
        return DeliveryMethod::create(array_merge([
            'name' => 'Standard Delivery',
            'code' => 'gift-'.Str::lower(Str::random(8)),
            'additional_fee' => '0.00',
            'is_pickup' => false,
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-GIFT-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Gift Customer',
            'customer_email' => 'gift@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Gift Street',
            'city' => 'Ampang',
            'state' => 'Selangor',
            'postcode' => '68000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Gift Street'],
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00',
            'shipping_fee' => '0.00',
            'total' => '100.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $attributes));
    }

    private function checkoutData(DeliveryMethod $method): array
    {
        return [
            'customer_name' => 'Gift Customer',
            'customer_email' => 'gift@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Gift Street',
            'city' => 'Ampang',
            'state' => 'Selangor',
            'postcode' => '68000',
            'country' => 'Malaysia',
            'delivery_method_id' => $method->id,
            'payment_method' => 'duitnow',
        ];
    }
}
