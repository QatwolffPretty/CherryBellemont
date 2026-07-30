<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Notifications\ReturnAdminNotification;
use App\Notifications\ReturnCustomerNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReturnNotifier
{
    public function __construct(private readonly OrderEmailLogService $logs) {}

    public function customer(ReturnRequest $returnRequest, string $event, bool $manualResend = false, ?int $resentBy = null): void
    {
        $recipient = strtolower(trim((string) $returnRequest->customer_email));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Customer return email skipped because no valid recipient is available.', ['return_number' => $returnRequest->return_number, 'event' => $event]);
            return;
        }

        $returnRequest->loadMissing('order', 'refunds');
        $log = $this->logs->prepare(
            $returnRequest->order,
            $event,
            $recipient,
            ['return_number' => $returnRequest->return_number, 'refund_id' => $returnRequest->refunds->sortByDesc('id')->first()?->id],
            $manualResend,
            $resentBy,
            $returnRequest->id,
        );
        if (! $manualResend && $log === null && Schema::hasTable('order_notification_logs')) return;

        $this->afterCommit(function () use ($returnRequest, $event, $recipient, $log): void {
            try {
                Notification::route('mail', $recipient)->notify(new ReturnCustomerNotification($returnRequest, $event, $log?->id));
            } catch (Throwable $exception) {
                $this->logs->markFailed($log?->id, $exception);
                Log::error('Customer return email could not be queued.', ['return_number' => $returnRequest->return_number, 'event' => $event, 'error' => str($exception->getMessage())->limit(300)->toString()]);
            }
        });
    }

    public function admin(ReturnRequest $returnRequest, string $event): void
    {
        $recipient = config('store.admin_notification_email') ?: config('mail.from.address');
        if (! $recipient) return;

        $this->afterCommit(function () use ($returnRequest, $event, $recipient): void {
            try {
                Notification::route('mail', $recipient)->notify(new ReturnAdminNotification($returnRequest, $event));
            } catch (Throwable $exception) {
                Log::error('Admin return email could not be queued.', ['return_number' => $returnRequest->return_number, 'event' => $event, 'error' => str($exception->getMessage())->limit(300)->toString()]);
            }
        });
    }

    private function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);
            return;
        }

        $callback();
    }
}
