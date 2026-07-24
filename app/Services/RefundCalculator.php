<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Validation\ValidationException;

class RefundCalculator
{
    /** @return array{item_amount: int, shipping_amount: int, gift_wrap_amount: int, total_amount: int, previous_refunds: int, remaining_amount: int} */
    public function calculate(ReturnRequest $request, int $shippingCents = 0, int $giftWrapCents = 0): array
    {
        $order = $request->order->loadMissing('items', 'refunds');
        $subtotalCents = $this->cents($order->subtotal);
        $discountCents = $this->cents($order->discount_amount ?? 0);
        $itemAmount = 0;

        foreach ($request->items as $returnItem) {
            $item = $order->items->firstWhere('id', $returnItem->order_item_id);
            $quantity = (int) ($returnItem->approved_quantity ?? 0);
            if (! $item || $quantity < 1 || $quantity > $item->quantity) throw ValidationException::withMessages(['items' => 'Approved return quantities are invalid.']);
            $lineCents = $this->cents($item->line_total ?? $item->total);
            $lineDiscount = $subtotalCents > 0 ? (int) round($discountCents * ($lineCents / $subtotalCents)) : 0;
            $itemAmount += (int) floor(max(0, $lineCents - $lineDiscount) * ($quantity / $item->quantity));
        }

        $previous = (int) round((float) $order->refunds()->where('status', 'succeeded')->sum('amount') * 100);
        $remaining = max(0, $this->cents($order->total) - $previous);
        if ($shippingCents > $this->cents($order->shipping_fee ?? 0)) {
            throw ValidationException::withMessages(['shipping_refund_amount' => 'Shipping refunds cannot exceed the shipping fee paid on this order.']);
        }
        if ($giftWrapCents > $this->cents($order->gift_wrapping_fee ?? 0)) {
            throw ValidationException::withMessages(['gift_wrap_refund_amount' => 'Gift wrapping refunds cannot exceed the gift wrapping fee paid on this order.']);
        }
        $total = $itemAmount + max(0, $shippingCents) + max(0, $giftWrapCents);
        if ($total > $remaining) throw ValidationException::withMessages(['refund' => 'The proposed refund exceeds the remaining amount paid.']);

        return ['item_amount' => $itemAmount, 'shipping_amount' => max(0, $shippingCents), 'gift_wrap_amount' => max(0, $giftWrapCents), 'total_amount' => $total, 'previous_refunds' => $previous, 'remaining_amount' => $remaining];
    }

    private function cents(mixed $amount): int { return (int) round(((float) $amount) * 100); }
}
