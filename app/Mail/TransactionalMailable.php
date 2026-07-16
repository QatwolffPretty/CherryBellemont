<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class TransactionalMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Configure future transactional messages to dispatch after a committed transaction. */
    protected function queueAfterCommit(): void
    {
        $this->afterCommit();
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Transactional email delivery failed.', [
            'mailable' => static::class,
            'email_type' => $this->emailType(),
            'error' => $exception->getMessage(),
        ]);
    }

    public function transactionalType(): string
    {
        return $this->emailType();
    }

    abstract protected function emailType(): string;
}
