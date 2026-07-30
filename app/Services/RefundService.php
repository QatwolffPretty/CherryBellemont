<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundService
{
    public function __construct(private readonly AccountingPostingService $accounting) {}

    public function confirm(Refund $refund): Refund
    {
        $confirmed = DB::transaction(function () use ($refund): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status === 'succeeded') return $refund;
            $order = Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $refund->update(['status' => 'succeeded', 'confirmed_at' => now(), 'failure_reason' => null]);
            $refunded = (float) $order->refunds()->where('status', 'succeeded')->sum('amount');
            $order->update(['refunded_amount' => number_format($refunded, 2, '.', ''), 'refund_status' => $refunded >= (float) $order->total ? 'refunded' : 'partially_refunded']);
            if ($refund->return_request_id) {
                $return = ReturnRequest::query()->lockForUpdate()->find($refund->return_request_id);
                if ($return && $return->status === 'resolution_pending') {
                    $return->update(['status' => 'completed', 'completed_at' => now()]);
                    $order->update(['return_status' => 'completed']);
                    ReturnRequestEvent::create(['return_request_id' => $return->id, 'actor_type' => 'system', 'event_type' => 'refund_succeeded', 'from_status' => 'resolution_pending', 'to_status' => 'completed', 'note' => 'Refund '.$refund->refund_number.' was confirmed.']);
                }
            }
            return $refund->fresh(['order', 'returnRequest']);
        }, 3);
        $post = function () use ($confirmed): void {
            try { $this->accounting->postCompletedRefund($confirmed); } catch (\Throwable $exception) { Log::error('Completed refund could not be posted to accounting.', ['refund_number' => $confirmed->refund_number, 'exception_class' => $exception::class]); }
        };
        if (DB::transactionLevel() > 0) DB::afterCommit($post); else $post();
        return $confirmed;
    }

    public function fail(Refund $refund, string $reason): Refund
    {
        return DB::transaction(function () use ($refund, $reason): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status === 'succeeded') return $refund;
            $refund->update(['status' => 'failed', 'failure_reason' => $reason]);
            $refund->order()->update(['refund_status' => 'failed']);
            if ($refund->return_request_id) {
                ReturnRequestEvent::create(['return_request_id' => $refund->return_request_id, 'actor_type' => 'system', 'event_type' => 'refund_failed', 'note' => $reason]);
            }
            return $refund->fresh(['order', 'returnRequest']);
        }, 3);
    }
}
