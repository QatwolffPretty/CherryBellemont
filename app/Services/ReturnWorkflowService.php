<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnWorkflowService
{
    private const TRANSITIONS = [
        'requested' => ['under_review'], 'under_review' => ['approved', 'rejected'], 'approved' => ['awaiting_return'],
        'awaiting_return' => ['item_received'], 'item_received' => ['inspecting'], 'inspecting' => ['resolution_pending', 'inspection_failed'],
        'resolution_pending' => ['completed'], 'rejected' => ['closed'], 'inspection_failed' => ['closed'], 'completed' => ['closed'],
    ];

    public function transition(ReturnRequest $request, string $target, ?User $actor = null, ?string $note = null, array $attributes = []): ReturnRequest
    {
        return DB::transaction(function () use ($request, $target, $actor, $note, $attributes): ReturnRequest {
            $request = ReturnRequest::query()->lockForUpdate()->findOrFail($request->id);
            $from = $request->status;
            if ($from === $target) return $request;
            if (! in_array($target, self::TRANSITIONS[$from] ?? [], true)) throw ValidationException::withMessages(['status' => 'This return status change is not allowed.']);
            $timestamps = match ($target) {
                'under_review' => ['reviewed_at' => now(), 'reviewed_by' => $actor?->id],
                'approved' => ['approved_at' => now(), 'reviewed_by' => $actor?->id],
                'rejected' => ['rejected_at' => now(), 'reviewed_by' => $actor?->id],
                'item_received' => ['item_received_at' => now()],
                'resolution_pending', 'inspection_failed' => ['inspected_at' => now()],
                'completed', 'closed' => ['completed_at' => now()], default => [],
            };
            $request->update(['status' => $target, ...$timestamps, ...$attributes]);
            $this->event($request, $actor, 'status_changed', $from, $target, $note);
            $request->order()->update(['return_status' => $target]);
            return $request->fresh(['items', 'order', 'refunds']);
        }, 3);
    }

    public function inspect(ReturnRequest $request, User $actor, array $items, bool $passed, string $note): array
    {
        $restocked = [];
        $updated = DB::transaction(function () use ($request, $actor, $items, $passed, $note, &$restocked): ReturnRequest {
            $request = ReturnRequest::query()->with('items')->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'inspecting') throw ValidationException::withMessages(['status' => 'Only requests under inspection can be completed.']);
            foreach ($request->items as $returnItem) {
                $data = $items[$returnItem->id] ?? null;
                if (! $data) throw ValidationException::withMessages(['items' => 'Every returned item requires an inspection result.']);
                $disposition = $data['stock_disposition'];
                $returnItem->update(['condition_received' => $data['condition_received'] ?? null, 'inspection_notes' => $data['inspection_notes'] ?? null, 'stock_disposition' => $disposition]);
                if ($passed && $disposition === 'restocked' && ! $returnItem->restocked_at && $returnItem->product_id) {
                    $product = Product::query()->lockForUpdate()->find($returnItem->product_id);
                    if ($product) { $previous = (int) $product->stock; $product->increment('stock', (int) ($returnItem->approved_quantity ?? 0)); $returnItem->update(['restocked_at' => now()]); $restocked[] = [$product->id, $previous]; }
                }
            }
            return $this->transition($request, $passed ? 'resolution_pending' : 'inspection_failed', $actor, $note);
        }, 3);
        return [$updated, $restocked];
    }

    public function event(ReturnRequest $request, ?User $actor, string $type, ?string $from = null, ?string $to = null, ?string $note = null, array $metadata = []): void
    {
        ReturnRequestEvent::create(['return_request_id' => $request->id, 'actor_type' => $actor ? 'admin' : 'system', 'actor_id' => $actor?->id, 'event_type' => $type, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'metadata' => $metadata]);
    }
}
