<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderCustomerNotification;
use App\Services\OrderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CustomerTransactionalEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_order_received_email_is_queued_with_a_secure_link_and_coupon_totals(): void
    {
        Notification::fake();
        $order = $this->order(['discount_amount' => '15.00', 'free_shipping_discount' => '5.00']);

        $this->assertTrue(app(OrderNotifier::class)->send($order, 'order_placed'));

        Notification::assertSentOnDemand(OrderCustomerNotification::class, function (OrderCustomerNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notification->event === 'order_placed'
                && $channels === ['mail']
                && $notifiable->routeNotificationFor('mail') === 'guest@example.test';
        });

        $notification = new OrderCustomerNotification($order, 'order_placed');
        $mail = $notification->toMail(new AnonymousNotifiable());
        $html = $this->render($order, 'order_placed');

        $this->assertSame('Cherry Bellemont Order Received — '.$order->order_number, $mail->subject);
        $this->assertStringContainsString('Product discount', $html);
        $this->assertStringContainsString('Complete DuitNow Payment', $html);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString($order->guest_access_token, $html);
    }

    public function test_receipt_rejection_email_includes_the_reason_without_exposing_a_receipt(): void
    {
        $order = $this->order();
        $html = $this->render($order, 'receipt_rejected', ['reason' => 'The image is unreadable.']);

        $this->assertStringContainsString('The image is unreadable.', $html);
        $this->assertStringContainsString('Replace Receipt', $html);
        $this->assertStringNotContainsString('payment-receipts/', $html);
    }

    public function test_stripe_confirmation_subject_is_used_only_for_a_paid_stripe_order(): void
    {
        $order = $this->order([
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'payment_status' => 'paid',
            'stripe_paid_at' => now(),
        ]);

        $mail = (new OrderCustomerNotification($order, 'payment_approved'))->toMail(new AnonymousNotifiable());
        $html = $this->render($order, 'payment_approved');

        $this->assertSame('Payment Confirmed — '.$order->order_number, $mail->subject);
        $this->assertStringContainsString('Payment method: Stripe', $html);
        $this->assertStringContainsString('confirmed on', $html);
    }

    public function test_shipped_order_email_requires_courier_and_tracking_details(): void
    {
        Notification::fake();
        $order = $this->order(['payment_status' => 'paid', 'order_status' => 'shipped']);
        $notifier = app(OrderNotifier::class);

        $this->assertFalse($notifier->send($order, 'status_updated'));
        Notification::assertNothingSent();

        $order->update(['courier_name' => 'DHL', 'tracking_number' => 'CB-TRACK-123', 'shipped_at' => now()]);
        $this->assertTrue($notifier->send($order->fresh(), 'status_updated', ['tracking_url' => 'https://tracking.example.test/CB-TRACK-123']));

        $html = $this->render($order->fresh(), 'status_updated', ['tracking_url' => 'https://tracking.example.test/CB-TRACK-123']);
        $this->assertStringContainsString('DHL', $html);
        $this->assertStringContainsString('CB-TRACK-123', $html);
        $this->assertStringContainsString('Track Shipment', $html);
    }

    public function test_processing_email_is_sent_only_when_the_fulfilment_status_changes(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order(['payment_status' => 'paid']);

        $this->actingAs($admin)
            ->patch(route('admin.orders.update', $order), ['order_status' => 'processing'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->patch(route('admin.orders.update', $order), ['order_status' => 'processing'])
            ->assertRedirect();

        Notification::assertSentOnDemand(OrderCustomerNotification::class, 1);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, function (OrderCustomerNotification $notification): bool {
            return $notification->event === 'status_updated'
                && $notification->order->order_status === 'processing';
        });
    }

    public function test_every_customer_notification_variant_renders_its_html_and_plain_text_and_is_queued(): void
    {
        Notification::fake();

        $variants = [
            ['order_placed', [], []],
            ['receipt_submitted', ['order_status' => 'payment_review'], []],
            ['payment_approved', ['payment_status' => 'paid'], []],
            ['receipt_rejected', ['order_status' => 'payment_review'], ['reason' => 'The receipt image is unreadable.']],
            ['payment_approved', ['payment_method' => 'stripe', 'payment_provider' => 'stripe', 'payment_status' => 'paid', 'stripe_paid_at' => now()], []],
            ['status_updated', ['payment_status' => 'paid', 'order_status' => 'processing'], []],
            ['status_updated', ['payment_status' => 'paid', 'order_status' => 'packed'], []],
            ['status_updated', ['payment_status' => 'paid', 'order_status' => 'shipped', 'courier_name' => 'DHL', 'tracking_number' => 'CB-TRACK-123', 'shipped_at' => now()], ['tracking_url' => 'https://tracking.example.test/CB-TRACK-123']],
            ['status_updated', ['payment_status' => 'paid', 'order_status' => 'delivered', 'delivered_at' => now()], []],
            ['status_updated', ['payment_status' => 'paid', 'order_status' => 'cancelled', 'cancellation_reason' => 'Customer request'], []],
        ];

        foreach ($variants as [$event, $attributes, $context]) {
            $order = $this->order($attributes);
            $notification = unserialize(serialize(new OrderCustomerNotification($order, $event, $context)));
            $mail = $notification->toMail(new AnonymousNotifiable());
            $html = view($mail->view['html'], $mail->viewData)->render();
            $text = view($mail->view['text'], $mail->viewData)->render();

            $this->assertNotEmpty($mail->subject);
            $this->assertStringContainsString($order->order_number, $html);
            $this->assertStringContainsString($order->order_number, $text);
            $this->assertTrue(app(OrderNotifier::class)->send($order, $event, $context));
        }

        Notification::assertSentOnDemand(OrderCustomerNotification::class, 10);
    }

    public function test_notification_is_skipped_when_customer_email_is_missing_or_invalid(): void
    {
        Notification::fake();

        $this->assertFalse(app(OrderNotifier::class)->send($this->order(['customer_email' => null]), 'order_placed'));
        $this->assertFalse(app(OrderNotifier::class)->send($this->order(['customer_email' => 'not-an-email']), 'order_placed'));

        Notification::assertNothingSent();
    }

    public function test_notification_queued_inside_a_rolled_back_transaction_is_not_dispatched(): void
    {
        Queue::fake();
        $order = $this->order();

        try {
            DB::transaction(function () use ($order): void {
                app(OrderNotifier::class)->send($order, 'order_placed');
                throw new RuntimeException('Rollback this test transaction.');
            });
        } catch (RuntimeException) {
            // Expected: the notification must remain undispatched after rollback.
        }

        Queue::assertNothingPushed();
    }

    public function test_sync_queue_mode_sends_a_guest_confirmation_without_creating_a_database_job(): void
    {
        config()->set('queue.default', 'sync');
        Mail::fake();
        $order = $this->order();

        $this->assertTrue(app(OrderNotifier::class)->send($order, 'order_placed'));

        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'notification_type' => 'order_placed',
            'status' => 'sent',
        ]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_legacy_queued_notification_without_a_delivery_log_identifier_renders_safely(): void
    {
        $order = $this->order();
        $reflection = new \ReflectionClass(OrderCustomerNotification::class);
        /** @var OrderCustomerNotification $notification */
        $notification = $reflection->newInstanceWithoutConstructor();
        $notification->order = $order;
        $notification->event = 'order_placed';
        $notification->context = [];

        $mail = $notification->toMail(new AnonymousNotifiable());

        $this->assertStringContainsString($order->order_number, $mail->subject);
        $this->assertNull($notification->emailLogId);
    }

    public function test_notification_dispatch_failure_does_not_change_a_valid_order(): void
    {
        $order = $this->order();
        $log = app(\App\Services\OrderEmailLogService::class)->prepare(
            $order,
            'order_placed',
            $order->customer_email,
        );
        $notification = new OrderCustomerNotification($order, 'order_placed', [], $log?->id);

        $notification->failed(new RuntimeException('Mail transport unavailable.'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_notification_logs', [
            'order_id' => $order->id,
            'notification_type' => 'order_placed',
            'status' => 'failed',
        ]);
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-EMAIL-'.Str::upper(Str::random(8));
        $order = Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Guest Customer',
            'customer_email' => 'guest@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street',
            'city' => 'Ampang',
            'state' => 'Selangor',
            'postcode' => '68000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Test Street'],
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'shipping_fee' => '10.00',
            'free_shipping_discount' => '0.00',
            'total' => '110.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $attributes));

        $product = Product::create([
            'name' => 'Email Piece '.Str::random(6),
            'description' => 'A considered piece.',
            'price' => '100.00',
            'stock' => 10,
            'status' => 'active',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '100.00',
            'total' => '100.00',
            'line_total' => '100.00',
        ]);

        return $order->fresh(['items.product', 'items.review']);
    }

    private function render(Order $order, string $event, array $context = []): string
    {
        return view('emails.customer.order-notification', $this->viewData($order, $event, $context))->render();
    }

    private function renderText(Order $order, string $event, array $context = []): string
    {
        return view('emails.customer.order-notification-text', $this->viewData($order, $event, $context))->render();
    }

    private function viewData(Order $order, string $event, array $context): array
    {
        return [
            'order' => $order->loadMissing('items.product', 'items.review'),
            'event' => $event,
            'context' => $context,
            'secureUrl' => route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]),
            'duitNowUrl' => route('orders.guest.duitnow', ['order' => $order->order_number, 'token' => $order->guest_access_token]),
            'stripeCheckoutUrl' => route('stripe.checkout.start', ['order' => $order->order_number, 'token' => $order->guest_access_token]),
            'reviewUrl' => null,
        ];
    }
}
