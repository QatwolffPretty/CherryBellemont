<?php

namespace App\Services;

use App\Jobs\QueueProductStockNotifications;
use App\Models\Product;
use App\Models\ProductStockNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductStockNotificationService
{
    /** @return 'created'|'duplicate'|'available' */
    public function request(Product $product, string $email, ?string $name): string
    {
        return DB::transaction(function () use ($product, $email, $name): string {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($product->status !== 'active' || $product->stock > 0) {
                return 'available';
            }

            $email = mb_strtolower(trim($email));
            $waiting = ProductStockNotification::query()
                ->where('product_id', $product->id)
                ->where('waiting_email', $email)
                ->lockForUpdate()
                ->first();

            if ($waiting) {
                return 'duplicate';
            }

            ProductStockNotification::create([
                'product_id' => $product->id,
                'email' => $email,
                'waiting_email' => $email,
                'name' => $name,
                'status' => ProductStockNotification::STATUS_WAITING,
                'notification_token' => Str::random(64),
                'requested_at' => now(),
            ]);

            return 'created';
        }, 3);
    }

    public function cancel(string $token): ProductStockNotification
    {
        return DB::transaction(function () use ($token): ProductStockNotification {
            $notification = ProductStockNotification::query()
                ->where('notification_token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($notification->status === ProductStockNotification::STATUS_WAITING) {
                $notification->update([
                    'status' => ProductStockNotification::STATUS_CANCELLED,
                    'waiting_email' => null,
                    'sending_at' => null,
                    'cancelled_at' => now(),
                ]);
            }

            return $notification->fresh();
        }, 3);
    }

    /**
     * Call this after a committed stock update. It intentionally only reacts
     * to a true 0-to-positive transition and queues no mail itself.
     */
    public function handleStockChange(Product $product, int $previousStock): void
    {
        if ($previousStock !== 0) {
            return;
        }

        $product = $product->fresh();
        if (! $product || $product->stock <= 0 || $product->status !== 'active') {
            return;
        }

        if (! $product->stockNotifications()->waiting()->exists()) {
            return;
        }

        try {
            QueueProductStockNotifications::dispatch($product->id);
        } catch (\Throwable $exception) {
            Log::warning('Back-in-stock notification queue dispatch failed.', [
                'product_id' => $product->id,
                'reason' => Str::limit($exception->getMessage(), 300),
            ]);
        }
    }

    /**
     * Claim one record for one queue attempt. A fresh duplicate job cannot
     * reclaim a record already in progress; queue retries may do so safely.
     *
     * @return array{notification: ProductStockNotification, product: Product}|null
     */
    public function claim(int $notificationId, bool $allowRetry = false): ?array
    {
        return DB::transaction(function () use ($notificationId, $allowRetry): ?array {
            $notification = ProductStockNotification::query()->lockForUpdate()->find($notificationId);
            if (! $notification || $notification->status !== ProductStockNotification::STATUS_WAITING) {
                return null;
            }
            if ($notification->sending_at && ! $allowRetry) {
                return null;
            }

            $product = Product::query()->lockForUpdate()->find($notification->product_id);
            if (! $product || $product->stock <= 0 || $product->status !== 'active') {
                $notification->update(['sending_at' => null]);

                return null;
            }

            $notification->update(['sending_at' => $notification->sending_at ?: now()]);

            return [
                'notification' => $notification->fresh(),
                'product' => $product->fresh(),
            ];
        }, 3);
    }

    public function markNotified(ProductStockNotification $notification): void
    {
        DB::transaction(function () use ($notification): void {
            $notification = ProductStockNotification::query()->lockForUpdate()->findOrFail($notification->id);
            if ($notification->status !== ProductStockNotification::STATUS_WAITING) {
                return;
            }

            $notification->update([
                'status' => ProductStockNotification::STATUS_NOTIFIED,
                'waiting_email' => null,
                'sending_at' => null,
                'notified_at' => now(),
            ]);
        }, 3);
    }

    public function releaseFailedAttempt(int $notificationId, \Throwable $exception): void
    {
        ProductStockNotification::query()
            ->whereKey($notificationId)
            ->where('status', ProductStockNotification::STATUS_WAITING)
            ->update(['sending_at' => null]);

        Log::warning('Back-in-stock email delivery failed.', [
            'notification_id' => $notificationId,
            'reason' => Str::limit($exception->getMessage(), 300),
        ]);
    }
}
