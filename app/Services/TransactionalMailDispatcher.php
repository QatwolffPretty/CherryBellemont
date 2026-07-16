<?php

namespace App\Services;

use App\Mail\TransactionalMailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransactionalMailDispatcher
{
    /**
     * Queue a transactional message without allowing a transient queue or mail
     * configuration problem to interrupt an originating customer workflow.
     */
    public function queue(string $recipient, TransactionalMailable $mailable): bool
    {
        try {
            Mail::to($recipient)->queue($mailable);

            return true;
        } catch (Throwable $exception) {
            Log::error('Transactional email could not be queued.', [
                'mailable' => $mailable::class,
                'email_type' => $mailable->transactionalType(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
