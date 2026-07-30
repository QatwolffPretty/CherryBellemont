<?php

namespace App\Notifications;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReturnCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    /**
     * Keep a default for jobs serialized before this optional delivery-log field was
     * added. This prevents unrelated legacy return jobs from repeatedly failing a
     * shared queue worker.
     */
    public ?int $emailLogId = null;

    public function __construct(public ReturnRequest $returnRequest, public string $event, ?int $emailLogId = null)
    {
        $this->emailLogId = $emailLogId;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $return = ReturnRequest::query()->with(['order.items.product', 'refunds'])->findOrFail($this->returnRequest->id);
        $order = $return->order;
        $secureUrl = $order?->order_number && $order?->guest_access_token
            ? route('returns.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token, 'returnRequest' => $return])
            : ($order?->user_id ? route('returns.show', ['order' => $order, 'returnRequest' => $return]) : null);

        $data = compact('return', 'order', 'secureUrl') + ['event' => $this->event];

        $subject = $this->subject($return);
        app(\App\Services\OrderEmailLogService::class)->markSent($this->emailLogId, $subject);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.customer.return-notification', $data)
            ->text('emails.customer.return-notification-text', $data);
    }

    public function failed(Throwable $exception): void
    {
        app(\App\Services\OrderEmailLogService::class)->markFailed($this->emailLogId, $exception);
        Log::warning('Customer return email delivery failed.', [
            'return_number' => $this->returnRequest->return_number,
            'notification_type' => $this->event,
            'error' => str($exception->getMessage())->limit(300)->toString(),
        ]);
    }

    private function subject(ReturnRequest $return): string
    {
        $prefix = match ($this->event) {
            'requested' => 'Return Request Received',
            'approved' => 'Your Return Request Has Been Approved',
            'rejected' => 'Return Request Update Required',
            'instructions' => 'Return Instructions for Your Order',
            'item_received' => 'Your Return Has Been Received',
            'inspection_failed' => 'Return Inspection Update',
            'refund_processing' => 'Your Refund Has Been Approved',
            'refund_succeeded' => 'Your Refund Has Been Completed',
            'refund_failed' => 'Refund Processing Update',
            'exchange_approved' => 'Your Exchange Request Has Been Approved',
            'closed' => 'Your Return Request Has Been Closed',
            default => 'Return Request Update',
        };

        return $prefix.' — '.$return->return_number;
    }
}
