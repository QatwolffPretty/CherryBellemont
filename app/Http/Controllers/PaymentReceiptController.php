<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function store(Request $request, Order $order, string $token): RedirectResponse
    {
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token) && $order->payment_method === 'duitnow', 403);
        $data = $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $order->paymentReceipts()->create(['path' => $data['receipt']->store('payment-receipts'), 'status' => 'pending']);
        return to_route('orders.guest.show', ['order' => $order->order_number, 'token' => $token])->with('success', 'Receipt uploaded for administrator review.');
    }
}
