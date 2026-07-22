<?php

namespace App\Jobs;

use App\Mail\BackInStockMail;
use App\Services\ProductStockNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendProductStockNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $notificationId)
    {
    }

    public function handle(ProductStockNotificationService $notifications): void
    {
        $claimed = $notifications->claim($this->notificationId, $this->attempts() > 1);
        if (! $claimed) {
            return;
        }

        Mail::to($claimed['notification']->email, $claimed['notification']->name)
            ->send(new BackInStockMail($claimed['notification'], $claimed['product']));

        $notifications->markNotified($claimed['notification']);
    }

    public function failed(Throwable $exception): void
    {
        app(ProductStockNotificationService::class)->releaseFailedAttempt($this->notificationId, $exception);
    }
}
