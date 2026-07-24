<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Services\RefundService;
use App\Services\StripeRefundService;
use App\Services\ReturnNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public function __construct(public int $refundId) {}

    public function handle(StripeRefundService $stripe, ReturnNotifier $notifier): void
    {
        $refund = Refund::query()->with(['order', 'returnRequest'])->find($this->refundId);
        if (! $refund || $refund->status !== 'processing' || $refund->stripe_refund_id) return;
        try {
            $result = $stripe->create($refund);
            $refund->update(['stripe_refund_id' => $result->id ?? null, 'processed_at' => now()]);
        } catch (\Throwable $exception) {
            $failedRefund = app(RefundService::class)->fail($refund, 'Stripe could not create the refund. Please retry.');
            if ($failedRefund->returnRequest) {
                $notifier->customer($failedRefund->returnRequest, 'refund_failed');
            }
            Log::warning('Stripe refund creation failed.', ['refund_id' => $refund->id, 'reason' => str($exception->getMessage())->limit(300)->toString()]);
            throw $exception;
        }
    }
}
