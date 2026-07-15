<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderCustomerNotification;
use Illuminate\Support\Facades\Notification;

class OrderNotifier
{
    public function send(Order $order, string $event, array $context = []): void
    {
        if ($order->customer_email && $order->guest_access_token) {
            Notification::route('mail', $order->customer_email)->notify(new OrderCustomerNotification($order, $event, $context));
        }
    }
}
