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

class OrderCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $event, public array $context = [])
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->orderForDelivery();
        $data = [
            'order' => $order,
            'event' => $this->event,
            'context' => $this->safeContext(),
            'secureUrl' => $this->secureOrderUrl($order),
            'duitNowUrl' => $this->guestRouteUrl('orders.guest.duitnow', $order),
            'stripeCheckoutUrl' => $this->guestRouteUrl('stripe.checkout.start', $order),
            'reviewUrl' => $this->reviewUrl($order),
        ];

        return (new MailMessage)
            ->subject($this->subject($order))
            ->view('emails.customer.order-notification', $data)
            ->text('emails.customer.order-notification-text', $data);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Customer order email delivery failed.', [
            'order_number' => $this->order->order_number,
            'notification_type' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }

    private function subject(Order $order): string
    {
        $subject = match ($this->event) {
            'order_placed' => 'Cherry Bellemont Order Received',
            'receipt_submitted' => 'Payment Receipt Received',
            'payment_approved' => $order->payment_method === 'stripe' ? 'Payment Confirmed' : 'Payment Approved',
            'receipt_rejected' => 'Action Required: Payment Receipt Rejected',
            'status_updated' => match ($order->order_status) {
                'processing' => 'We’re Preparing Your Order',
                'packed' => 'Your Order Has Been Packed',
                'shipped' => 'Your Order Is On The Way',
                'delivered' => 'Your Cherry Bellemont Order Has Arrived',
                'cancelled' => 'Order Cancelled',
                default => 'Cherry Bellemont Order Update',
            },
            default => 'Cherry Bellemont Order Update',
        };

        return $subject." \u{2014} ".$order->order_number;
    }

    private function orderForDelivery(): Order
    {
        return Order::query()
            ->with(['items.product', 'items.review', 'paymentReceipts', 'coupon', 'deliveryMethod'])
            ->findOrFail($this->order->getKey());
    }

    /** @return array{reason: ?string, tracking_url: ?string} */
    private function safeContext(): array
    {
        $reason = $this->context['reason'] ?? null;
        $trackingUrl = $this->context['tracking_url'] ?? null;

        return [
            'reason' => is_scalar($reason) && trim((string) $reason) !== '' ? trim((string) $reason) : null,
            'tracking_url' => is_string($trackingUrl) && filter_var($trackingUrl, FILTER_VALIDATE_URL) ? $trackingUrl : null,
        ];
    }

    private function secureOrderUrl(Order $order): ?string
    {
        if ($guestUrl = $this->guestRouteUrl('orders.guest.show', $order)) {
            return $guestUrl;
        }

        return $order->user_id ? route('orders.show', ['order' => $order]) : null;
    }

    private function guestRouteUrl(string $routeName, Order $order): ?string
    {
        if (! $order->order_number || ! $order->guest_access_token) {
            return null;
        }

        return route($routeName, [
            'order' => $order->order_number,
            'token' => $order->guest_access_token,
        ]);
    }

    private function reviewUrl(Order $order): ?string
    {
        if ($this->event !== 'status_updated' || $order->order_status !== 'delivered' || ! $order->guest_access_token) {
            return null;
        }

        $item = $order->items
            ->first(fn ($item) => $item->product && $item->product->status === 'active' && ! $item->review);

        if (! $item?->product) {
            return null;
        }

        return route('reviews.create', [
            'product' => $item->product,
            'order_number' => $order->order_number,
            'guest_access_token' => $order->guest_access_token,
            'customer_email' => $order->customer_email,
        ]);
    }
}
