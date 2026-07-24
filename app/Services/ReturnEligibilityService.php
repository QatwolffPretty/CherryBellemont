<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReturnEligibilityService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function canRequest(Order $order): bool
    {
        return $order->payment_status === 'paid'
            && $order->order_status === 'delivered'
            && $order->delivered_at
            && $order->delivered_at->greaterThanOrEqualTo(now()->subDays($this->windowDays()))
            && $this->eligibleItems($order)->isNotEmpty();
    }

    public function eligibleItems(Order $order)
    {
        if ($order->payment_status !== 'paid' || $order->order_status !== 'delivered' || ! $order->delivered_at) return collect();
        if ($order->delivered_at->lt(Carbon::now()->subDays($this->windowDays()))) return collect();

        return $order->items->map(function (OrderItem $item): ?OrderItem {
            $approved = (int) ReturnRequestItem::query()->where('order_item_id', $item->id)->sum('approved_quantity');
            $item->setAttribute('returnable_quantity', max(0, (int) $item->quantity - $approved));

            return $item->returnable_quantity > 0 ? $item : null;
        })->filter()->values();
    }

    public function validateItem(Order $order, int $orderItemId, int $quantity): OrderItem
    {
        $item = $order->items->firstWhere('id', $orderItemId);
        if (! $item) throw ValidationException::withMessages(['items' => 'A selected item does not belong to this order.']);
        $eligible = $this->eligibleItems($order)->firstWhere('id', $item->id);
        if (! $eligible) throw ValidationException::withMessages(['items' => 'This item is not eligible for a return request.']);
        if ($quantity < 1 || $quantity > $eligible->returnable_quantity) throw ValidationException::withMessages(['items' => 'Requested quantity exceeds the remaining returnable quantity.']);

        return $item;
    }

    private function windowDays(): int
    {
        return max(1, (int) $this->settings->get('returns.window_days', config('store.returns.return_window_days', 14)));
    }
}
