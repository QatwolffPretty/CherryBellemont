<?php

namespace App\Services;

use App\Notifications\AdminOperationalNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AdminNotificationService
{
    /**
     * Queue concise operational mail only after its originating transaction commits.
     * Queue failures remain non-blocking for commerce and administration workflows.
     */
    public function send(string $event, array $data = []): bool
    {
        $recipient = config('store.admin_notification_email') ?: config('mail.from.address');

        if (! $recipient) {
            Log::warning('Admin operational email was skipped because no recipient is configured.', ['event' => $event]);

            return false;
        }

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($recipient, $event, $data): void {
                $this->dispatch($recipient, $event, $data);
            });

            return true;
        }

        return $this->dispatch($recipient, $event, $data);
    }

    private function dispatch(string $recipient, string $event, array $data): bool
    {
        try {
            Notification::route('mail', $recipient)->notify(new AdminOperationalNotification($event, $data));

            return true;
        } catch (Throwable $exception) {
            Log::error('Admin operational email could not be queued.', [
                'event' => $event,
                'order_number' => ($data['order'] ?? null)?->order_number,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
