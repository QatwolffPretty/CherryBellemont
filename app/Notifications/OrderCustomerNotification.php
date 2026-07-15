<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class OrderCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $event, public array $context = []) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $subjects = [
            'order_placed' => 'Cherry Bellemont Order Received — ',
            'receipt_submitted' => 'Payment Receipt Received — ',
            'payment_approved' => 'Payment Approved — ',
            'receipt_rejected' => 'Action Required: Receipt Rejected — ',
            'status_updated' => ($this->order->order_status === 'shipped' ? 'Your Order Has Shipped — ' : 'Order Update — '),
        ];

        $data = [
                'order' => $this->order->loadMissing('items'),
                'event' => $this->event,
                'context' => $this->context,
                'secureUrl' => route('orders.guest.show', ['order' => $this->order->order_number, 'token' => $this->order->guest_access_token]),
            ];

        return (new MailMessage)
            ->subject(($subjects[$this->event] ?? 'Cherry Bellemont Order Update — ').$this->order->order_number)
            ->view('emails.orders.notification', $data)
            ->text('emails.orders.notification-text', $data);
    }
}
