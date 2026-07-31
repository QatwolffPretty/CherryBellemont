<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Synchronises historical, completed business events into the existing
 * double-entry journal. Journal source keys keep every run idempotent.
 */
class AccountingBackfillService
{
    public function __construct(
        private readonly AccountingPostingService $posting,
        private readonly AccountingAuditService $audit,
    ) {}

    /**
     * @param  Closure(string, string, string): void|null  $progress
     * @return array<string, int>
     */
    public function run(?string $from = null, ?string $to = null, bool $dryRun = false, int $chunk = 100, ?Closure $progress = null): array
    {
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : null;
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : null;
        $summary = [
            'orders_scanned' => 0, 'orders_posted' => 0, 'orders_already_posted' => 0,
            'refunds_scanned' => 0, 'refunds_posted' => 0, 'refunds_already_posted' => 0,
            'outside_range' => 0, 'failures' => 0,
        ];

        Order::query()->where('payment_status', 'paid')->with(['items', 'paymentReceipts'])
            ->orderBy('id')->chunkById($chunk, function ($orders) use (&$summary, $start, $end, $dryRun, $progress): void {
                foreach ($orders as $order) {
                    $summary['orders_scanned']++;
                    if (! $this->withinRange($this->orderPaymentDate($order), $start, $end)) {
                        $summary['outside_range']++;

                        continue;
                    }

                    $exists = $this->sourceExists('order', $order->id, 'paid');
                    if ($exists) {
                        $summary['orders_already_posted']++;
                        $progress?->__invoke('order', 'already-posted', $order->order_number);

                        continue;
                    }

                    if ($dryRun) {
                        $summary['orders_posted']++;
                        $progress?->__invoke('order', 'would-post', $order->order_number);

                        continue;
                    }

                    try {
                        $this->posting->postPaidOrder($order, force: true);
                        $summary['orders_posted']++;
                        $this->audit->record('accounting.backfill.order_synchronized', $order, null, [], ['source_event' => 'paid']);
                        $progress?->__invoke('order', 'posted', $order->order_number);
                    } catch (\Throwable $exception) {
                        $summary['failures']++;
                        $this->audit->record('accounting.backfill.order_failed', $order, null, [], ['exception_class' => $exception::class]);
                        Log::error('Paid order accounting backfill failed.', ['order_number' => $order->order_number, 'exception_class' => $exception::class]);
                        $progress?->__invoke('order', 'failed', $order->order_number);
                    }
                }
            });

        Refund::query()->where('status', 'succeeded')->with(['order', 'returnRequest.items.orderItem'])
            ->orderBy('id')->chunkById($chunk, function ($refunds) use (&$summary, $start, $end, $dryRun, $progress): void {
                foreach ($refunds as $refund) {
                    $summary['refunds_scanned']++;
                    if (! $this->withinRange(CarbonImmutable::parse($refund->confirmed_at ?? $refund->updated_at), $start, $end)) {
                        $summary['outside_range']++;

                        continue;
                    }

                    $exists = $this->sourceExists('refund', $refund->id, 'completed');
                    if ($exists) {
                        $summary['refunds_already_posted']++;
                        $progress?->__invoke('refund', 'already-posted', $refund->refund_number);

                        continue;
                    }

                    if ($dryRun) {
                        $summary['refunds_posted']++;
                        $progress?->__invoke('refund', 'would-post', $refund->refund_number);

                        continue;
                    }

                    try {
                        $this->posting->postCompletedRefund($refund);
                        $summary['refunds_posted']++;
                        $this->audit->record('accounting.backfill.refund_synchronized', $refund, null, [], ['source_event' => 'completed']);
                        $progress?->__invoke('refund', 'posted', $refund->refund_number);
                    } catch (\Throwable $exception) {
                        $summary['failures']++;
                        $this->audit->record('accounting.backfill.refund_failed', $refund, null, [], ['exception_class' => $exception::class]);
                        Log::error('Completed refund accounting backfill failed.', ['refund_number' => $refund->refund_number, 'exception_class' => $exception::class]);
                        $progress?->__invoke('refund', 'failed', $refund->refund_number);
                    }
                }
            });

        return $summary;
    }

    private function sourceExists(string $type, int $id, string $event): bool
    {
        return JournalEntry::query()->where([
            'source_type' => $type,
            'source_id' => $id,
            'source_event' => $event,
        ])->exists();
    }

    private function orderPaymentDate(Order $order): CarbonImmutable
    {
        $receiptDate = optional($order->paymentReceipts->where('status', 'approved')->sortByDesc('reviewed_at')->first())->reviewed_at;

        return CarbonImmutable::parse($order->stripe_paid_at ?? $receiptDate ?? $order->updated_at);
    }

    private function withinRange(CarbonImmutable $date, ?CarbonImmutable $start, ?CarbonImmutable $end): bool
    {
        return (! $start || $date->greaterThanOrEqualTo($start))
            && (! $end || $date->lessThanOrEqualTo($end));
    }
}
