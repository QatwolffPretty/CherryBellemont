<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\View\View;

class ShipmentTrackingController extends Controller
{
    public function __invoke(Order $order, string $token, SettingsService $settings): View
    {
        abort_unless((bool) $settings->get('shipment.customer_tracking_enabled', true), 404);
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token), 403);

        return view('shipments.track', [
            'order' => $order->load('latestShipment.courier', 'latestShipment.events'),
            'token' => $token,
        ]);
    }
}
