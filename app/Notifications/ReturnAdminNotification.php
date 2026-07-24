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

class ReturnAdminNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(public ReturnRequest $returnRequest, public string $event)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $return = ReturnRequest::query()->with('order')->findOrFail($this->returnRequest->id);
        $data = ['return' => $return, 'order' => $return->order, 'event' => $this->event, 'actionUrl' => route('admin.returns.show', $return)];

        return (new MailMessage)
            ->subject(($this->event === 'new_request' ? 'New Return Request' : 'Return Action Required').' — '.$return->return_number)
            ->view('emails.admin.return-notification', $data)
            ->text('emails.admin.return-notification-text', $data);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Admin return email delivery failed.', [
            'return_number' => $this->returnRequest->return_number,
            'notification_type' => $this->event,
            'error' => str($exception->getMessage())->limit(300)->toString(),
        ]);
    }
}
