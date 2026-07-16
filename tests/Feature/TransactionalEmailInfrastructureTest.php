<?php

namespace Tests\Feature;

use App\Mail\TransactionalPreviewMail;
use App\Mail\AdminOperationalPreviewMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\TransactionalMailDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class TransactionalEmailInfrastructureTest extends TestCase
{
    public function test_the_transactional_preview_mailable_is_queued_and_renders_the_shared_layout(): void
    {
        $mailable = new TransactionalPreviewMail('order-received');

        $this->assertInstanceOf(ShouldQueue::class, $mailable);
        $this->assertStringContainsString('CHERRY BELLEMONT', $mailable->render());
        $this->assertStringContainsString('Order received', $mailable->render());
        $this->assertStringContainsString(config('store.support_email'), $mailable->render());

        Mail::fake();
        $queued = app(TransactionalMailDispatcher::class)->queue('preview@example.test', $mailable);

        $this->assertTrue($queued);
        Mail::assertQueued(TransactionalPreviewMail::class, fn (TransactionalPreviewMail $mail) => $mail->previewType === 'order-received');
    }

    public function test_each_customer_transactional_email_preview_renders_from_the_shared_template(): void
    {
        foreach ([
            'order-received',
            'receipt-submitted',
            'payment-approved',
            'receipt-rejected',
            'stripe-payment-confirmed',
            'processing',
            'packed',
            'shipped',
            'delivered',
            'cancelled',
        ] as $previewType) {
            $this->assertStringContainsString('CHERRY BELLEMONT', (new TransactionalPreviewMail($previewType))->render());
        }

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('dev.email.order-received'));
    }

    public function test_each_admin_operational_email_preview_renders_from_the_shared_template(): void
    {
        foreach ([
            'new-order',
            'new-duitnow-receipt',
            'stripe-paid',
            'low-stock',
            'out-of-stock',
            'new-review',
            'new-newsletter-subscriber',
            'cancelled-order',
            'payment-attention',
        ] as $previewType) {
            $this->assertStringContainsString('CHERRY BELLEMONT', (new AdminOperationalPreviewMail($previewType))->render());
        }
    }

    public function test_customer_order_notifications_render_through_the_shared_email_layout(): void
    {
        $order = new Order([
            'order_number' => 'CB-EMAIL-20260716',
            'customer_name' => 'Cherry Bellemont Guest',
            'subtotal' => 248.00,
            'shipping_fee' => 12.00,
            'total' => 260.00,
            'payment_method' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_method_name' => 'Standard Delivery',
        ]);
        $order->setRelation('items', new Collection([
            new OrderItem(['product_name' => 'Cherry Bellemont Signature Piece', 'quantity' => 1, 'line_total' => 248.00]),
        ]));

        $html = view('emails.customer.order-notification', [
            'order' => $order,
            'event' => 'order_placed',
            'context' => [],
            'secureUrl' => url('/'),
            'duitNowUrl' => url('/'),
            'stripeCheckoutUrl' => url('/'),
            'reviewUrl' => null,
        ])->render();

        $this->assertStringContainsString('CHERRY BELLEMONT', $html);
        $this->assertStringContainsString('Order received', $html);
        $this->assertStringContainsString('Cherry Bellemont Signature Piece', $html);
    }

    public function test_a_failed_transactional_mailable_logs_useful_non_sensitive_context(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Transactional email delivery failed.'
                && $context['mailable'] === TransactionalPreviewMail::class
                && $context['email_type'] === 'preview.shipped'
                && $context['error'] === 'mail transport unavailable';
        });

        (new TransactionalPreviewMail('shipped'))->failed(new RuntimeException('mail transport unavailable'));
    }
}
