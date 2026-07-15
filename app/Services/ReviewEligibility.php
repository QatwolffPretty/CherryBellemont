<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewEligibility
{
    /** @return array{order: Order, orderItem: OrderItem, review: ?Review} */
    public function resolve(Request $request, Product $product): array
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:100'],
            'guest_access_token' => ['nullable', 'string', 'size:64'],
            'customer_email' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::query()->where('order_number', $data['order_number'])->firstOrFail();
        $authenticatedOwner = $request->user() && $order->user_id === $request->user()->id;
        $validGuestToken = $order->guest_access_token
            && ! empty($data['guest_access_token'])
            && hash_equals($order->guest_access_token, $data['guest_access_token']);

        abort_unless($authenticatedOwner || $validGuestToken, 403);
        abort_unless(hash_equals(mb_strtolower($order->customer_email), mb_strtolower($data['customer_email'])), 403);

        if ($order->payment_status !== 'paid' || $order->order_status !== 'delivered') {
            throw ValidationException::withMessages([
                'review' => 'Reviews are available only after payment and delivery are complete.',
            ]);
        }

        $item = $order->items()
            ->where('product_id', $product->id)
            ->with('review')
            ->orderBy('id')
            ->firstOrFail();

        return ['order' => $order, 'orderItem' => $item, 'review' => $item->review];
    }

    public function assertReviewMatches(Review $review, array $context): void
    {
        abort_unless(
            $review->order_id === $context['order']->id
            && $review->order_item_id === $context['orderItem']->id,
            403,
        );
    }
}
