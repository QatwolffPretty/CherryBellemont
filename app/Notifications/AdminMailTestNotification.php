<?php

namespace App\Notifications;

use App\Services\OrderEmailLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class AdminMailTestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $subjectLine,
        public ?string $messageBody = null,
        public ?int $emailLogId = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(OrderEmailLogService::class)->markSent($this->emailLogId, $this->subjectLine);

        $data = [
            'subjectLine' => $this->subjectLine,
            'messageBody' => $this->messageBody ?: 'This is a Cherry Bellemont email delivery test. If you can read it in Mailpit, local mail delivery is configured correctly.',
        ];

        return (new MailMessage)
            ->subject($this->subjectLine)
            ->view('emails.admin.mail-test', $data)
            ->text('emails.admin.mail-test-text', $data);
    }

    public function failed(Throwable $exception): void
    {
        app(OrderEmailLogService::class)->markFailed($this->emailLogId, $exception);
    }
}
