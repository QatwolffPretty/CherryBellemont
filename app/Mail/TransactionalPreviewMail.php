<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TransactionalPreviewMail extends TransactionalMailable
{
    public function __construct(public readonly string $previewType)
    {
        $this->queueAfterCommit();

        if (! array_key_exists($previewType, $this->messages())) {
            throw new InvalidArgumentException("Unsupported transactional email preview [{$previewType}].");
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->message()['subject'].' — CB-PREVIEW-20260716');
    }

    public function content(): Content
    {
        $message = $this->message();
        $order = $this->previewOrder($message);

        return new Content(
            view: 'emails.customer.order-notification',
            text: 'emails.customer.order-notification-text',
            with: [
                'order' => $order,
                'event' => $message['event'],
                'context' => $message['context'] ?? [],
                'secureUrl' => url('/'),
                'duitNowUrl' => url('/'),
                'stripeCheckoutUrl' => url('/'),
                'reviewUrl' => $message['review_url'] ?? null,
            ],
        );
    }

    protected function emailType(): string
    {
        return 'preview.'.$this->previewType;
    }

    /** @return array{subject: string, event: string, payment_method: string, payment_status: string, order_status: string, context?: array<string, string>, review_url?: string} */
    private function message(): array
    {
        return $this->messages()[$this->previewType];
    }

    /** @return array<string, array{subject: string, event: string, payment_method: string, payment_status: string, order_status: string, context?: array<string, string>, review_url?: string}> */
    private function messages(): array
    {
        return [
            'order-received' => ['subject' => 'Cherry Bellemont Order Received', 'event' => 'order_placed', 'payment_method' => 'duitnow', 'payment_status' => 'pending', 'order_status' => 'pending'],
            'receipt-submitted' => ['subject' => 'Payment Receipt Received', 'event' => 'receipt_submitted', 'payment_method' => 'duitnow', 'payment_status' => 'pending', 'order_status' => 'payment_review'],
            'payment-approved' => ['subject' => 'Payment Approved', 'event' => 'payment_approved', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'pending'],
            'receipt-rejected' => ['subject' => 'Action Required: Payment Receipt Rejected', 'event' => 'receipt_rejected', 'payment_method' => 'duitnow', 'payment_status' => 'pending', 'order_status' => 'payment_review', 'context' => ['reason' => 'The receipt image is not clear enough to verify.']],
            'stripe-payment-confirmed' => ['subject' => 'Payment Confirmed', 'event' => 'payment_approved', 'payment_method' => 'stripe', 'payment_status' => 'paid', 'order_status' => 'pending'],
            'processing' => ['subject' => 'We’re Preparing Your Order', 'event' => 'status_updated', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'processing'],
            'packed' => ['subject' => 'Your Order Has Been Packed', 'event' => 'status_updated', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'packed'],
            'shipped' => ['subject' => 'Your Order Is On The Way', 'event' => 'status_updated', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'shipped', 'context' => ['tracking_url' => 'https://tracking.example.test/CB-PREVIEW-20260716']],
            'delivered' => ['subject' => 'Your Cherry Bellemont Order Has Arrived', 'event' => 'status_updated', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'delivered', 'review_url' => 'https://example.test/review'],
            'cancelled' => ['subject' => 'Order Cancelled', 'event' => 'status_updated', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'cancelled'],
        ];
    }

    /** @param array{payment_method: string, payment_status: string, order_status: string} $message */
    private function previewOrder(array $message): Order
    {
        $order = new Order([
            'order_number' => 'CB-PREVIEW-20260716',
            'customer_name' => 'Cherry Bellemont Guest',
            'customer_email' => 'guest@example.test',
            'subtotal' => 348.00,
            'discount_amount' => 20.00,
            'shipping_fee' => 12.00,
            'free_shipping_discount' => 0.00,
            'total' => 340.00,
            'payment_method' => $message['payment_method'],
            'payment_status' => $message['payment_status'],
            'order_status' => $message['order_status'],
            'shipping_method_name' => 'Standard Delivery',
            'courier_name' => $message['order_status'] === 'shipped' ? 'DHL' : null,
            'tracking_number' => $message['order_status'] === 'shipped' ? 'CB-TRACK-123' : null,
            'cancellation_reason' => $message['order_status'] === 'cancelled' ? 'Customer request' : null,
            'created_at' => now(),
            'stripe_paid_at' => $message['payment_method'] === 'stripe' ? now() : null,
            'shipped_at' => $message['order_status'] === 'shipped' ? now() : null,
            'delivered_at' => $message['order_status'] === 'delivered' ? now() : null,
        ]);

        $order->setRelation('items', new Collection([
            new OrderItem(['product_name' => 'Cherry Bellemont Signature Piece', 'quantity' => 1, 'unit_price' => 248.00, 'line_total' => 248.00]),
            new OrderItem(['product_name' => 'Cherry Bellemont Gift Wrap', 'quantity' => 1, 'unit_price' => 100.00, 'line_total' => 100.00]),
        ]));

        return $order;
    }
}
