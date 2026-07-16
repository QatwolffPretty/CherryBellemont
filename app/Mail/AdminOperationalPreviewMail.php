<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AdminOperationalPreviewMail extends TransactionalMailable
{
    public function __construct(public readonly string $previewType)
    {
        $this->queueAfterCommit();

        if (! array_key_exists($previewType, $this->definitions())) {
            throw new InvalidArgumentException("Unsupported admin email preview [{$previewType}].");
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->definition()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.operational-notification',
            text: 'emails.admin.operational-notification-text',
            with: $this->viewData(),
        );
    }

    protected function emailType(): string
    {
        return 'admin-preview.'.$this->previewType;
    }

    /** @return array{event: string, subject: string} */
    private function definition(): array
    {
        return $this->definitions()[$this->previewType];
    }

    /** @return array<string, array{event: string, subject: string}> */
    private function definitions(): array
    {
        return [
            'new-order' => ['event' => 'new_order', 'subject' => 'New Cherry Bellemont Order — CB-ADMIN-PREVIEW'],
            'new-duitnow-receipt' => ['event' => 'new_duitnow_receipt', 'subject' => 'New DuitNow Receipt Awaiting Review — CB-ADMIN-PREVIEW'],
            'stripe-paid' => ['event' => 'stripe_payment_confirmed', 'subject' => 'Stripe Payment Confirmed — CB-ADMIN-PREVIEW'],
            'low-stock' => ['event' => 'low_stock', 'subject' => 'Low Stock Alert — Cherry Bellemont Signature Piece'],
            'out-of-stock' => ['event' => 'out_of_stock', 'subject' => 'Product Out of Stock — Cherry Bellemont Signature Piece'],
            'new-review' => ['event' => 'new_review', 'subject' => 'New Product Review Awaiting Approval'],
            'new-newsletter-subscriber' => ['event' => 'new_newsletter_subscriber', 'subject' => 'New Newsletter Subscriber'],
            'cancelled-order' => ['event' => 'order_cancelled', 'subject' => 'Order Cancelled — CB-ADMIN-PREVIEW'],
            'payment-attention' => ['event' => 'payment_attention', 'subject' => 'Payment Processing Attention Required'],
        ];
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $definition = $this->definition();
        $order = $this->order($definition['event']);
        $product = $this->product();
        $receipt = $this->receipt($order);
        $review = $this->review($product);
        $subscriber = $this->subscriber();

        $data = [
            'event' => $definition['event'],
            'title' => match ($definition['event']) {
                'new_order' => 'A new order has been placed',
                'new_duitnow_receipt' => 'A DuitNow receipt is awaiting review',
                'stripe_payment_confirmed' => 'A Stripe payment has been confirmed',
                'low_stock' => 'A product has reached the low-stock threshold',
                'out_of_stock' => 'A product is out of stock',
                'new_review' => 'A customer review is awaiting moderation',
                'new_newsletter_subscriber' => 'A new customer joined the newsletter',
                'order_cancelled' => 'An order has been cancelled',
                default => 'A payment-processing issue needs attention',
            },
            'status' => match ($definition['event']) {
                'stripe_payment_confirmed' => 'Payment paid',
                'new_duitnow_receipt', 'new_review', 'payment_attention' => 'Action required',
                'out_of_stock' => 'Out of stock',
                'low_stock' => 'Low stock',
                'order_cancelled' => 'Order cancelled',
                default => 'New activity',
            },
            'statusTone' => $definition['event'] === 'stripe_payment_confirmed' ? 'success' : 'pending',
            'actionUrl' => url('/admin'),
            'actionLabel' => 'View in admin',
            'order' => in_array($definition['event'], ['new_order', 'new_duitnow_receipt', 'stripe_payment_confirmed', 'order_cancelled', 'payment_attention'], true) ? $order : null,
            'receipt' => $definition['event'] === 'new_duitnow_receipt' ? $receipt : null,
            'product' => in_array($definition['event'], ['low_stock', 'out_of_stock'], true) ? $product : null,
            'review' => $definition['event'] === 'new_review' ? $review : null,
            'subscriber' => $definition['event'] === 'new_newsletter_subscriber' ? $subscriber : null,
        ];

        if ($definition['event'] === 'order_cancelled') {
            $data['order']->setAttribute('cancellation_reason', 'Customer request');
            $data['order']->setAttribute('stock_restored_at', now());
        }
        if ($definition['event'] === 'payment_attention') {
            $data += ['provider' => 'Stripe', 'reference' => 'evt_preview_attention', 'summary' => 'The verified payment amount did not match the order total.', 'occurredAt' => now()];
        }

        return $data;
    }

    private function order(string $event): Order
    {
        $order = new Order([
            'order_number' => 'CB-ADMIN-PREVIEW',
            'customer_name' => 'Cherry Bellemont Guest',
            'customer_email' => 'guest@example.test',
            'customer_phone' => '0123456789',
            'payment_method' => $event === 'stripe_payment_confirmed' ? 'stripe' : 'duitnow',
            'payment_status' => in_array($event, ['stripe_payment_confirmed', 'order_cancelled'], true) ? 'paid' : 'pending',
            'order_status' => $event === 'order_cancelled' ? 'cancelled' : 'pending',
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => 248.00,
            'discount_amount' => 20.00,
            'shipping_fee' => 12.00,
            'total' => 240.00,
            'stripe_paid_at' => $event === 'stripe_payment_confirmed' ? now() : null,
        ]);
        $order->setAttribute('id', 1);
        $order->exists = true;
        $order->setRelation('items', new Collection([
            new OrderItem(['product_name' => 'Cherry Bellemont Signature Piece', 'quantity' => 1, 'unit_price' => 248.00, 'line_total' => 248.00]),
        ]));

        return $order;
    }

    private function product(): Product
    {
        $product = new Product(['name' => 'Cherry Bellemont Signature Piece', 'stock' => 2]);
        $product->setAttribute('id', 1);
        $product->exists = true;

        return $product;
    }

    private function receipt(Order $order): PaymentReceipt
    {
        $receipt = new PaymentReceipt(['status' => 'pending', 'submitted_at' => now()]);
        $receipt->setAttribute('id', 1);
        $receipt->exists = true;
        $receipt->setRelation('order', $order);

        return $receipt;
    }

    private function review(Product $product): Review
    {
        $review = new Review(['customer_name' => 'Cherry Bellemont Guest', 'rating' => 5, 'title' => 'A beautiful piece', 'created_at' => now()]);
        $review->setAttribute('id', 1);
        $review->exists = true;
        $review->setRelation('product', $product);

        return $review;
    }

    private function subscriber(): NewsletterSubscriber
    {
        $subscriber = new NewsletterSubscriber(['name' => 'Cherry Bellemont Guest', 'email' => 'guest@example.test', 'source' => 'newsletter_section', 'subscribed_at' => now()]);
        $subscriber->setAttribute('id', 1);
        $subscriber->exists = true;

        return $subscriber;
    }
}
