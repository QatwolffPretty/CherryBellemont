<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderCustomerNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class OrderNotifier
{
    /**
     * Queue a customer notification after its originating transaction has committed.
     * A queue dispatch issue is logged and never interrupts the customer workflow.
     */
    public function send(Order $order, string $event, array $context = []): bool
    {
        $recipient = trim((string) $order->customer_email);

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Customer order email was skipped because no valid customer email is available.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
            ]);

            return false;
        }

        if (! $order->guest_access_token && ! $order->user_id) {
            Log::warning('Customer order email was skipped because no secure order access route is available.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
            ]);

            return false;
        }

        if ($event === 'status_updated' && $order->order_status === 'shipped' && (! $order->courier_name || ! $order->tracking_number)) {
            Log::warning('Customer order email was skipped because courier details are incomplete.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
            ]);

            return false;
        }

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($order, $event, $context, $recipient): void {
                $this->dispatch($order, $event, $context, $recipient);
            });

            return true;
        }

        return $this->dispatch($order, $event, $context, $recipient);
    }

    private function dispatch(Order $order, string $event, array $context, string $recipient): bool
    {
        try {
            Notification::route('mail', $recipient)
                ->notify(new OrderCustomerNotification($order, $event, $context));
        } catch (Throwable $exception) {
            Log::error('Customer order email could not be queued.', [
                'order_number' => $order->order_number,
                'notification_type' => $event,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
