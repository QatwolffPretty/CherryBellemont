<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReviewSubmissionService
{
    /** @param array<string, mixed> $data */
    public function create(Product $product, array $context, array $data): Review
    {
        $paths = [];

        try {
            return DB::transaction(function () use ($product, $context, $data, &$paths): Review {
                $order = Order::query()->lockForUpdate()->findOrFail($context['order']->id);
                $item = OrderItem::query()->lockForUpdate()->findOrFail($context['orderItem']->id);
                $this->assertEligible($order, $item, $product);

                if (Review::query()->where('order_item_id', $item->id)->exists()) {
                    throw ValidationException::withMessages(['review' => 'This purchased item already has a review. You may edit it instead.']);
                }

                $review = Review::create($this->reviewAttributes($order, $item, $product, $data));
                $this->storeImages($review, $data['images'] ?? [], $paths);

                return $review->load('images');
            });
        } catch (Throwable $exception) {
            $this->deletePaths($paths);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Review $review, Product $product, array $context, array $data): Review
    {
        $newPaths = [];
        $removedPaths = [];

        try {
            $updated = DB::transaction(function () use ($review, $product, $context, $data, &$newPaths, &$removedPaths): Review {
                $review = Review::query()->lockForUpdate()->findOrFail($review->id);
                abort_unless($review->order_id === $context['order']->id && $review->order_item_id === $context['orderItem']->id, 403);

                $order = Order::query()->lockForUpdate()->findOrFail($review->order_id);
                $item = OrderItem::query()->lockForUpdate()->findOrFail($review->order_item_id);
                $this->assertEligible($order, $item, $product);

                $removeIds = collect($data['remove_images'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                $imagesToRemove = $review->images()->whereIn('id', $removeIds)->get();
                $remainingCount = $review->images()->count() - $imagesToRemove->count();
                $newImages = $data['images'] ?? [];

                if ($remainingCount + count($newImages) > 5) {
                    throw ValidationException::withMessages(['images' => 'A review may include a maximum of 5 photos.']);
                }

                foreach ($imagesToRemove as $image) {
                    $removedPaths[] = $image->image_path;
                    $image->delete();
                }

                $review->update([
                    'rating' => $data['rating'],
                    'title' => $data['title'],
                    'review' => $data['review'],
                    'is_approved' => false,
                    'status' => 'pending',
                    'approved_at' => null,
                    'admin_reply' => null,
                ]);
                $this->storeImages($review, $newImages, $newPaths);

                return $review->fresh('images');
            });

            $this->deletePaths($removedPaths);

            return $updated;
        } catch (Throwable $exception) {
            $this->deletePaths($newPaths);
            throw $exception;
        }
    }

    private function assertEligible(Order $order, OrderItem $item, Product $product): void
    {
        if ($order->payment_status !== 'paid' || $order->order_status !== 'delivered') {
            throw ValidationException::withMessages(['review' => 'Reviews are available only after payment and delivery are complete.']);
        }

        if ($item->order_id !== $order->id || $item->product_id !== $product->id) {
            abort(403);
        }
    }

    /** @param array<string, mixed> $data */
    private function reviewAttributes(Order $order, OrderItem $item, Product $product, array $data): array
    {
        return [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'customer_name' => $order->customer_name,
            'customer_email' => mb_strtolower($order->customer_email),
            'rating' => $data['rating'],
            'title' => $data['title'],
            'review' => $data['review'],
            'is_verified_purchase' => true,
            'is_approved' => false,
            'status' => 'pending',
        ];
    }

    /** @param array<int, UploadedFile> $files @param array<int, string> $paths */
    private function storeImages(Review $review, array $files, array &$paths): void
    {
        $sortOrder = (int) ($review->images()->max('sort_order') ?? -1) + 1;

        foreach ($files as $file) {
            $path = $file->store('review-images', 'public');
            $paths[] = $path;
            $review->images()->create(['image_path' => $path, 'sort_order' => $sortOrder++]);
        }
    }

    /** @param array<int, string> $paths */
    private function deletePaths(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }
}
