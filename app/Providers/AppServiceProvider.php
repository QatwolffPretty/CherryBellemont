<?php

namespace App\Providers;

use App\Notifications\OrderCustomerNotification;
use App\Services\OrderEmailLogService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $trustedProxies = config('proxy.trusted_proxies', []);
        if ($trustedProxies !== []) {
            TrustProxies::at($trustedProxies);
        }

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->channel !== 'mail' || ! $event->notification instanceof OrderCustomerNotification) {
                return;
            }

            $notification = $event->notification;

            try {
                app(OrderEmailLogService::class)->markSent(
                    $notification->emailLogId,
                    $notification->mailSubject ?? 'Cherry Bellemont Order Update',
                );

                Log::info('Customer order notification was sent.', [
                    'order_number' => $notification->order->order_number,
                    'notification_type' => $notification->event,
                    'delivery_log_id' => $notification->emailLogId,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Customer order email was sent but its delivery log could not be updated.', [
                    'order_number' => $notification->order->order_number,
                    'notification_type' => $notification->event,
                    'exception_class' => $exception::class,
                ]);
            }
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->channel !== 'mail' || ! $event->notification instanceof OrderCustomerNotification) {
                return;
            }

            $notification = $event->notification;
            $exception = $event->data['exception'] ?? null;

            if (! $exception instanceof Throwable) {
                return;
            }

            try {
                app(OrderEmailLogService::class)->markFailed($notification->emailLogId, $exception);
            } catch (Throwable $loggingException) {
                Log::warning('Customer order email failure could not be recorded.', [
                    'order_number' => $notification->order->order_number,
                    'notification_type' => $notification->event,
                    'exception_class' => $loggingException::class,
                ]);
            }

            Log::warning('Customer order notification failed.', [
                'order_number' => $notification->order->order_number,
                'notification_type' => $notification->event,
                'exception_class' => $exception::class,
            ]);
        });
    }
}
