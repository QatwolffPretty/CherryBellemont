<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Notifications\OrderCustomerNotification;
use App\Notifications\AdminOperationalNotification;
use App\Services\StripeCheckoutService;
use App\Services\StripeWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stripe.webhook_secret', 'whsec_cherry_bellemont_test');
        config()->set('stripe.currency', 'myr');
    }

    public function test_guest_can_choose_stripe_checkout_and_server_totals_ignore_browser_values(): void
    {
        Notification::fake();
        $product = $this->product(['price' => '100.00', 'stock' => 5]);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '10.00']);

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('beginCheckout')->once()->with(Mockery::on(function (Order $order): bool {
            $this->assertSame('stripe', $order->payment_method);
            $this->assertSame('stripe', $order->payment_provider);
            $this->assertSame('200.00', $order->subtotal);
            $this->assertSame('10.00', $order->shipping_fee);
            $this->assertSame('210.00', $order->total);

            return true;
        }))->andReturnUsing(function (Order $order): object {
            $order->update(['stripe_checkout_session_id' => 'cs_test_initial', 'stripe_payment_status' => 'unpaid']);

            return (object) ['id' => 'cs_test_initial', 'url' => 'https://checkout.stripe.test/cs_test_initial', 'payment_status' => 'unpaid'];
        });
        $this->app->instance(StripeCheckoutService::class, $stripe);

        $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), array_merge($this->checkoutData($method), ['payment_method' => 'stripe', 'subtotal' => 1, 'shipping_fee' => 1, 'total' => 1]))
            ->assertRedirect('https://checkout.stripe.test/cs_test_initial');

        $order = Order::query()->firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('cs_test_initial', $order->stripe_checkout_session_id);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_stripe_payload_uses_order_snapshots_and_includes_shipping_in_sen(): void
    {
        $order = $this->stripeOrder(['subtotal' => '200.00', 'shipping_fee' => '10.00', 'total' => '210.00']);
        $order->items()->create(['name' => 'Silk Dress', 'product_name' => 'Silk Dress', 'quantity' => 2, 'unit_price' => '100.00', 'total' => '200.00', 'line_total' => '200.00']);

        $payload = app(StripeCheckoutService::class)->checkoutPayload($order);

        $this->assertSame('payment', $payload['mode']);
        $this->assertSame('myr', $payload['line_items'][0]['price_data']['currency']);
        $this->assertSame(10000, $payload['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame(1000, $payload['line_items'][1]['price_data']['unit_amount']);
        $this->assertSame('Shipping — Standard Delivery', $payload['line_items'][1]['price_data']['product_data']['name']);
        $this->assertSame(21000, collect($payload['line_items'])->sum(fn (array $line) => $line['price_data']['unit_amount'] * $line['quantity']));
        $this->assertSame($order->order_number, $payload['client_reference_id']);
        $this->assertSame((string) $order->id, $payload['metadata']['order_id']);
        $this->assertSame('stripe', $payload['metadata']['payment_provider']);
        $this->assertSame(route('stripe.success').'?session_id={CHECKOUT_SESSION_ID}', $payload['success_url']);
        $this->assertStringNotContainsString('%7B', $payload['success_url']);
        $this->assertStringStartsWith('http', $payload['cancel_url']);
    }

    public function test_stripe_session_initialization_failure_returns_to_checkout_not_cancellation(): void
    {
        Notification::fake();
        $product = $this->product(['stock' => 5]);
        $method = $this->deliveryMethod();
        $this->shippingZone(['base_fee' => '10.00']);

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('beginCheckout')->once()->andThrow(new RuntimeException('Stripe rejected the configured secret key.'));
        $stripe->shouldReceive('recordCheckoutFailure')->once()->andReturnUsing(function (Order $order): void {
            $order->update([
                'stripe_payment_status' => 'failed',
                'stripe_failure_reason' => 'Unable to start Stripe Checkout. Please try again.',
            ]);
        });
        $this->app->instance(StripeCheckoutService::class, $stripe);

        $response = $this->withSession(['cart' => [$product->id => 1]])
            ->from(route('checkout.create'))
            ->post(route('checkout.store'), array_merge($this->checkoutData($method), ['payment_method' => 'stripe']));

        $response->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors('stripe')
            ->assertSessionHas('stripe_pending_order', fn (array $pending): bool => isset($pending['order'], $pending['token']));

        $order = Order::query()->firstOrFail();
        $this->assertSame('failed', $order->stripe_payment_status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->get(route('checkout.create'))->assertOk()->assertSee('Retry Card Payment');
    }

    public function test_stripe_success_page_does_not_mark_an_order_paid(): void
    {
        Notification::fake();
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_success']);
        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('retrieveCheckoutSession')->once()->with('cs_test_success')->andReturn((object) [
            'id' => 'cs_test_success',
            'payment_status' => 'paid',
            'metadata' => (object) ['order_id' => (string) $order->id, 'order_number' => $order->order_number],
        ]);
        $this->app->instance(StripeCheckoutService::class, $stripe);

        $this->get(route('stripe.success', ['session_id' => 'cs_test_success']))->assertOk()->assertSee('Payment confirmation is processing');

        $this->assertSame('pending', $order->fresh()->payment_status);
        Notification::assertNothingSent();
    }

    public function test_valid_stripe_webhook_marks_payment_paid_once_without_changing_stock(): void
    {
        Notification::fake();
        $product = $this->product(['stock' => 5]);
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_paid', 'total' => '110.00']);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => '100.00', 'total' => '100.00', 'line_total' => '100.00']);

        $this->postStripeEvent($this->sessionEvent($order, 'evt_paid', 11000, 'myr'))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pi_test_paid', $order->fresh()->stripe_payment_intent_id);
        $this->assertNotNull($order->fresh()->stripe_paid_at);
        $this->assertSame(5, $product->fresh()->stock);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'payment_approved');
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'stripe_payment_confirmed');
    }

    public function test_payment_intent_succeeded_marks_an_order_paid_using_its_saved_intent_id(): void
    {
        Notification::fake();
        $order = $this->stripeOrder(['stripe_payment_intent_id' => 'pi_test_fallback']);

        $this->postStripeEvent($this->paymentIntentEvent($order, 'evt_pi_fallback', 'pi_test_fallback', 10000, 'myr'))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->fresh()->stripe_payment_status);
        $this->assertSame('pi_test_fallback', $order->fresh()->stripe_payment_intent_id);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, 1);
    }

    public function test_payment_intent_succeeded_can_resolve_the_related_checkout_session_as_a_fallback(): void
    {
        Notification::fake();
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_pi_session']);
        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('findCheckoutSessionForPaymentIntent')->once()->with('pi_test_session_fallback')->andReturn((object) [
            'id' => 'cs_test_pi_session',
            'payment_status' => 'paid',
            'amount_total' => 10000,
            'currency' => 'myr',
            'payment_intent' => 'pi_test_session_fallback',
            'metadata' => (object) [],
        ]);
        $stripe->shouldReceive('amountInSen')->once()->with('100.00')->andReturn(10000);
        $stripe->shouldReceive('currency')->once()->andReturn('myr');

        $event = (object) [
            'id' => 'evt_pi_session_fallback',
            'type' => 'payment_intent.succeeded',
            'data' => (object) ['object' => (object) [
                'id' => 'pi_test_session_fallback',
                'amount_received' => 10000,
                'currency' => 'myr',
                'metadata' => (object) [],
            ]],
        ];

        app(StripeWebhookService::class, ['stripe' => $stripe])->process($event, ['id' => $event->id, 'type' => $event->type]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pi_test_session_fallback', $order->fresh()->stripe_payment_intent_id);
    }

    public function test_invalid_stripe_webhook_signature_is_rejected(): void
    {
        $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid'], '{}')
            ->assertBadRequest();
    }

    public function test_duplicate_stripe_webhook_is_idempotent(): void
    {
        Notification::fake();
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_duplicate']);
        $event = $this->sessionEvent($order, 'evt_duplicate', 10000, 'myr');

        $this->postStripeEvent($event)->assertOk();
        $this->postStripeEvent($event)->assertOk();

        $this->assertDatabaseCount('stripe_webhook_events', 1);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, 1);
        Notification::assertSentOnDemand(AdminOperationalNotification::class, 1);
    }

    public function test_amount_mismatch_does_not_mark_order_paid(): void
    {
        Notification::fake();
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_amount']);

        $this->postStripeEvent($this->sessionEvent($order, 'evt_amount_mismatch', 9999, 'myr'))->assertServerError();

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('Stripe payment amount verification failed.', $order->fresh()->stripe_failure_reason);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => 'evt_amount_mismatch', 'processed_at' => null]);
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'payment_attention');
    }

    public function test_currency_mismatch_does_not_mark_order_paid(): void
    {
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_currency']);

        $this->postStripeEvent($this->sessionEvent($order, 'evt_currency_mismatch', 10000, 'usd'))->assertServerError();

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('Stripe payment currency verification failed.', $order->fresh()->stripe_failure_reason);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => 'evt_currency_mismatch', 'processed_at' => null]);
    }

    public function test_missing_stripe_order_is_logged_as_a_retryable_webhook_failure(): void
    {
        $this->postStripeEvent([
            'id' => 'evt_missing_order',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_missing_order',
                'payment_status' => 'paid',
                'amount_total' => 10000,
                'currency' => 'myr',
                'payment_intent' => 'pi_missing_order',
                'metadata' => [],
                'client_reference_id' => 'CB-MISSING',
            ]],
        ])->assertServerError();

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_missing_order',
            'processed_at' => null,
        ]);
    }

    public function test_retry_creates_a_new_session_without_a_duplicate_order_or_stock_deduction(): void
    {
        $product = $this->product(['stock' => 4]);
        $order = $this->stripeOrder(['stripe_checkout_session_id' => 'cs_test_old']);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => '100.00', 'total' => '100.00', 'line_total' => '100.00']);

        $stripe = Mockery::mock(StripeCheckoutService::class);
        $stripe->shouldReceive('beginCheckout')->once()->with(Mockery::on(fn (Order $candidate) => $candidate->is($order)), true)->andReturnUsing(function (Order $candidate): object {
            $candidate->update(['stripe_checkout_session_id' => 'cs_test_retry', 'stripe_payment_status' => 'unpaid', 'stripe_failure_reason' => null]);

            return (object) ['id' => 'cs_test_retry', 'url' => 'https://checkout.stripe.test/cs_test_retry'];
        });
        $this->app->instance(StripeCheckoutService::class, $stripe);

        $this->post(route('stripe.retry', ['order' => $order->order_number, 'token' => $order->guest_access_token]))
            ->assertRedirect('https://checkout.stripe.test/cs_test_retry');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('cs_test_retry', $order->fresh()->stripe_checkout_session_id);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_paid_stripe_order_cannot_retry(): void
    {
        $order = $this->stripeOrder(['payment_status' => 'paid']);

        $this->post(route('stripe.retry', ['order' => $order->order_number, 'token' => $order->guest_access_token]))->assertStatus(422);
    }

    public function test_duitnow_checkout_remains_on_the_manual_payment_flow(): void
    {
        $product = $this->product(['stock' => 2]);
        $pickup = $this->deliveryMethod(['is_pickup' => true]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->checkoutData($pickup))
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('duitnow', $order->payment_method);
        $this->assertSame('duitnow', $order->payment_provider);
        $this->get(route('orders.guest.duitnow', ['order' => $order->order_number, 'token' => $order->guest_access_token]))->assertOk();
    }

    public function test_non_admin_cannot_access_stripe_order_admin_details(): void
    {
        $customer = User::factory()->create();
        $order = $this->stripeOrder();

        $this->actingAs($customer)->get(route('admin.orders.show', $order))->assertForbidden();
    }

    private function postStripeEvent(array $event)
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('stripe.webhook_secret'));

        return $this->call('POST', route('stripe.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }

    private function sessionEvent(Order $order, string $eventId, int $amount, string $currency): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $order->stripe_checkout_session_id,
                'amount_total' => $amount,
                'currency' => $currency,
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_paid',
                'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->order_number, 'payment_provider' => 'stripe'],
            ]],
        ];
    }

    private function paymentIntentEvent(Order $order, string $eventId, string $paymentIntentId, int $amount, string $currency): array
    {
        return [
            'id' => $eventId,
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => $paymentIntentId,
                'amount_received' => $amount,
                'currency' => $currency,
                'status' => 'succeeded',
                'metadata' => ['order_id' => (string) $order->id, 'order_number' => $order->order_number, 'payment_provider' => 'stripe'],
            ]],
        ];
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Stripe Dress '.Str::random(6), 'description' => 'A considered piece.', 'price' => '100.00', 'status' => 'active', 'stock' => 10], $attributes));
    }

    private function shippingZone(array $attributes = []): ShippingZone
    {
        return ShippingZone::create(array_merge(['name' => 'Stripe Zone '.Str::random(6), 'state' => 'Selangor', 'city_or_area' => 'Ampang', 'base_fee' => '8.00', 'is_active' => true, 'sort_order' => 0], $attributes));
    }

    private function deliveryMethod(array $attributes = []): DeliveryMethod
    {
        return DeliveryMethod::create(array_merge(['name' => 'Standard Delivery', 'code' => 'stripe-'.Str::lower(Str::random(8)), 'additional_fee' => '0.00', 'is_pickup' => false, 'is_active' => true, 'sort_order' => 0], $attributes));
    }

    private function stripeOrder(array $attributes = []): Order
    {
        $number = 'CB-STRIPE-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64),
            'customer_name' => 'Stripe Guest', 'customer_email' => 'stripe@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Test Street'], 'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00', 'shipping_fee' => '0.00', 'total' => '100.00', 'payment_method' => 'stripe', 'payment_provider' => 'stripe',
            'payment_status' => 'pending', 'order_status' => 'pending', 'status' => 'pending',
        ], $attributes));
    }

    private function checkoutData(DeliveryMethod $method): array
    {
        return [
            'customer_name' => 'Stripe Guest', 'customer_email' => 'stripe@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'delivery_method_id' => $method->id, 'payment_method' => 'duitnow',
        ];
    }
}
