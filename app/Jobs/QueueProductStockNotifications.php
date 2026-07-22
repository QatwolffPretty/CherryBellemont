<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductStockNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueProductStockNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $productId)
    {
    }

    public function handle(): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product || $product->stock <= 0 || $product->status !== 'active') {
            return;
        }

        ProductStockNotification::query()
            ->where('product_id', $product->id)
            ->waiting()
            ->whereNull('sending_at')
            ->orderBy('id')
            ->chunkById(100, function ($notifications): void {
                foreach ($notifications as $notification) {
                    SendProductStockNotification::dispatch($notification->id);
                }
            });
    }
}
