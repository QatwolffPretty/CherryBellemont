<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderCustomerNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderNotifier
{
    public function __construct(private readonly OrderEmailLogService $logs) {}

    /**
     * Queue a customer notification after its originating transaction has committed.
     * A queue dispatch issue is logged and never interrupts the customer workflow.
     */
    public function send(Order $order, string $event, array $context = [], bool $manualResend = false, ?int $resentBy = null): bool
    {
        $recipientSource = filled($order->customer_email) ? 'customer_email' : 'email';
        $recipient = trim((string) ($order->customer_email ?: $order->email));

        Log::info('Customer order notification was attempted.', $this->logContext($order, $event) + [
            'recipient_source' => $recipientSource,
            'manual_resend' => $manualResend,
        ]);

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Customer order email was skipped because no valid customer email is available.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
            ]);

            return false;
        }

        if (! $order->guest_access_token && ! $order->user_id) {
            Log::warning('Customer order email was skipped because no secure order access route is available.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
            ]);

            return false;
        }

        if ($event === 'status_updated' && $order->order_status === 'shipped' && (! $order->courier_name || ! $order->tracking_number)) {
            Log::warning('Customer order email was skipped because courier details are incomplete.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
            ]);

            return false;
        }

        $context = $this->eventContext($order, $event, $context);
        $log = $this->logs->prepare($order, $event, $recipient, $context, $manualResend, $resentBy);

        if (! $manualResend && $log === null && Schema::hasTable('order_notification_logs')) {
            return false;
        }

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($order, $event, $context, $recipient, $log): void {
                $this->dispatch($order, $event, $context, $recipient, $log?->id);
            });

            return true;
        }

        return $this->dispatch($order, $event, $context, $recipient, $log?->id);
    }

    private function dispatch(Order $order, string $event, array $context, string $recipient, ?int $logId): bool
    {
        try {
            Notification::route('mail', $recipient)
                ->notify(new OrderCustomerNotification($order, $event, $context, $logId));
        } catch (Throwable $exception) {
            $this->logs->markFailed($logId, $exception);
            Log::error('Customer order email could not be queued.', [
                ...$this->logContext($order, $event),
                'exception_class' => $exception::class,
            ]);

            return false;
        }

        Log::info('Customer order notification was queued.', $this->logContext($order, $event) + [
            'queue_connection' => config('queue.default'),
            'delivery_log_id' => $logId,
        ]);

        return true;
    }

    /** @return array{order_number: ?string, notification_type: string} */
    private function logContext(Order $order, string $event): array
    {
        return [
            'order_number' => $order->order_number,
            'notification_type' => $event,
        ];
    }

    /** @return array<string, mixed> */
    private function eventContext(Order $order, string $event, array $context): array
    {
        if ($event === 'status_updated') {
            $context['order_status'] = $order->order_status;
        }

        if (in_array($event, ['receipt_submitted', 'receipt_rejected'], true) && empty($context['receipt_id'])) {
            $context['receipt_id'] = $order->paymentReceipts()->latest('id')->value('id');
        }

        return $context;
    }
}
