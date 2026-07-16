<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminOperationalNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $event, public array $data = [])
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->view('emails.admin.operational-notification', $this->viewData())
            ->text('emails.admin.operational-notification-text', $this->viewData());
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Admin operational email delivery failed.', [
            'event' => $this->event,
            'order_number' => ($this->data['order'] ?? null)?->order_number,
            'error' => $exception->getMessage(),
        ]);
    }

    private function subject(): string
    {
        $orderNumber = ($this->data['order'] ?? null)?->order_number;

        return match ($this->event) {
            'new_order' => 'New Cherry Bellemont Order — '.$orderNumber,
            'new_duitnow_receipt' => 'New DuitNow Receipt Awaiting Review — '.$orderNumber,
            'stripe_payment_confirmed' => 'Stripe Payment Confirmed — '.$orderNumber,
            'duitnow_payment_approved' => 'DuitNow Payment Approved — '.$orderNumber,
            'low_stock' => 'Low Stock Alert — '.($this->data['product']?->name ?? 'Product'),
            'out_of_stock' => 'Product Out of Stock — '.($this->data['product']?->name ?? 'Product'),
            'new_review' => 'New Product Review Awaiting Approval',
            'new_newsletter_subscriber' => 'New Newsletter Subscriber',
            'order_cancelled' => 'Order Cancelled — '.$orderNumber,
            'payment_attention' => 'Payment Processing Attention Required',
            default => 'Cherry Bellemont Operations Update',
        };
    }

    private function viewData(): array
    {
        $order = $this->data['order'] ?? null;
        $receipt = $this->data['receipt'] ?? null;
        $product = $this->data['product'] ?? null;
        $review = $this->data['review'] ?? null;
        $subscriber = $this->data['subscriber'] ?? null;

        return $this->data + [
            'event' => $this->event,
            'title' => match ($this->event) {
                'new_order' => 'A new order has been placed',
                'new_duitnow_receipt' => 'A DuitNow receipt is awaiting review',
                'stripe_payment_confirmed' => 'A Stripe payment has been confirmed',
                'duitnow_payment_approved' => 'A DuitNow payment has been approved',
                'low_stock' => 'A product has reached the low-stock threshold',
                'out_of_stock' => 'A product is out of stock',
                'new_review' => 'A customer review is awaiting moderation',
                'new_newsletter_subscriber' => 'A new customer joined the newsletter',
                'order_cancelled' => 'An order has been cancelled',
                'payment_attention' => 'A payment-processing issue needs attention',
                default => 'Operations update',
            },
            'status' => match ($this->event) {
                'stripe_payment_confirmed', 'duitnow_payment_approved' => 'Payment paid',
                'new_duitnow_receipt', 'new_review', 'payment_attention' => 'Action required',
                'out_of_stock' => 'Out of stock',
                'low_stock' => 'Low stock',
                'order_cancelled' => 'Order cancelled',
                default => 'New activity',
            },
            'statusTone' => in_array($this->event, ['stripe_payment_confirmed', 'duitnow_payment_approved'], true) ? 'success' : 'pending',
            'actionUrl' => $this->actionUrl($order, $receipt, $product, $review),
            'actionLabel' => $this->actionLabel(),
            'order' => $order,
            'receipt' => $receipt,
            'product' => $product,
            'review' => $review,
            'subscriber' => $subscriber,
        ];
    }

    private function actionUrl(?Order $order, mixed $receipt, mixed $product, mixed $review): ?string
    {
        return match ($this->event) {
            'new_order', 'stripe_payment_confirmed', 'duitnow_payment_approved', 'order_cancelled' => $order ? route('admin.orders.show', $order) : null,
            'new_duitnow_receipt' => $receipt ? route('admin.payment-receipts.show', $receipt) : null,
            'low_stock', 'out_of_stock' => $product ? route('admin.products.edit', $product) : null,
            'new_review' => $review ? route('admin.reviews.show', $review) : null,
            'new_newsletter_subscriber' => route('admin.newsletter.index'),
            'payment_attention' => $order ? route('admin.orders.show', $order) : null,
            default => null,
        };
    }

    private function actionLabel(): string
    {
        return match ($this->event) {
            'new_duitnow_receipt' => 'Review receipt',
            'low_stock', 'out_of_stock' => 'Manage product',
            'new_review' => 'Review moderation',
            'new_newsletter_subscriber' => 'View subscribers',
            'payment_attention' => 'Review payment',
            default => 'View order',
        };
    }
}
