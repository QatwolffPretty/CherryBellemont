<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderNotificationLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class OrderEmailLogService
{
    public function prepare(
        ?Order $order,
        string $notificationType,
        string $recipient,
        array $metadata = [],
        bool $manualResend = false,
        ?int $resentBy = null,
        ?int $returnRequestId = null,
    ): ?OrderNotificationLog {
        if (! Schema::hasTable('order_notification_logs')) {
            return null;
        }

        $eventKey = $manualResend
            ? 'manual:'.Str::uuid()
            : $this->eventKey($order, $notificationType, $metadata, $returnRequestId);

        try {
            return OrderNotificationLog::query()->create([
                'order_id' => $order?->id,
                'return_request_id' => $returnRequestId,
                'notification_type' => $notificationType,
                'recipient' => strtolower(trim($recipient)),
                'event_key' => $eventKey,
                'status' => 'queued',
                'is_manual_resend' => $manualResend,
                'resent_by' => $manualResend ? $resentBy : null,
                'queued_at' => now(),
                'metadata' => $this->safeMetadata($metadata),
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateKey($exception)) {
                Log::info('Duplicate customer email was skipped.', [
                    'order_number' => $order?->order_number,
                    'notification_type' => $notificationType,
                ]);

                return null;
            }

            Log::warning('Customer email was queued without a delivery log.', [
                'order_number' => $order?->order_number,
                'notification_type' => $notificationType,
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }

    public function markSent(?int $logId, string $subject): void
    {
        if (! $logId || ! Schema::hasTable('order_notification_logs')) {
            return;
        }

        OrderNotificationLog::query()->whereKey($logId)->update([
            'subject' => Str::limit($subject, 255, ''),
            'status' => 'sent',
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
            'attempts' => DB::raw('attempts + 1'),
        ]);
    }

    public function markFailed(?int $logId, Throwable $exception): void
    {
        if (! $logId || ! Schema::hasTable('order_notification_logs')) {
            return;
        }

        OrderNotificationLog::query()->whereKey($logId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => Str::limit(trim($exception->getMessage()), 500, ''),
        ]);
    }

    private function eventKey(?Order $order, string $type, array $metadata, ?int $returnRequestId): string
    {
        $state = match ($type) {
            'status_updated' => (string) ($metadata['order_status'] ?? $order?->order_status ?? 'unknown'),
            'shipment_updated' => implode(':', [(string) ($metadata['shipment_id'] ?? ''), (string) ($metadata['shipment_status'] ?? '')]),
            'receipt_submitted', 'receipt_rejected' => (string) ($metadata['receipt_id'] ?? 'current'),
            'refund_processing', 'refund_succeeded', 'refund_failed' => (string) ($metadata['refund_id'] ?? $returnRequestId ?? 'current'),
            default => (string) ($metadata['event_version'] ?? 'current'),
        };

        return implode(':', ['order', $order?->getKey() ?? 'mail-test', $returnRequestId ?? 0, $type, $state]);
    }

    /** @return array<string, scalar|null> */
    private function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->except(['token', 'guest_access_token', 'password', 'secret', 'receipt_path'])
            ->map(function (mixed $value): string|int|float|bool|null {
                if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                    return $value;
                }

                return Str::limit(trim((string) $value), 500, '');
            })
            ->all();
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique');
    }
}
