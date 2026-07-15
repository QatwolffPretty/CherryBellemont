<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View { return view('orders.index', ['orders' => $request->user()->orders()->latest()->paginate(12)]); }
    public function show(Request $request, Order $order): View { $this->authorizeOrder($request, $order); return view('orders.show', ['order' => $order->load('items.product', 'items.review', 'paymentReceipts')]); }
    public function confirmation(Request $request, Order $order): View { $this->authorizeOrder($request, $order); return view('orders.confirmation', ['order' => $order->load('items')]); }
    public function guestShow(Order $order, string $token): View { $this->authorizeGuest($order, $token); return view('orders.show', ['order' => $order->load('items.product', 'items.review', 'paymentReceipts'), 'token' => $token]); }
    public function guestConfirmation(Order $order, string $token): View { $this->authorizeGuest($order, $token); return view('orders.confirmation', ['order' => $order->load('items'), 'token' => $token]); }
    public function guestDuitNowInstructions(Order $order, string $token): View { $this->authorizeGuest($order, $token); abort_unless($order->payment_method === 'duitnow', 404); return view('orders.duitnow', compact('order', 'token')); }
    private function authorizeOrder(Request $request, Order $order): void { abort_unless($order->user_id === $request->user()->id, 403); }
    private function authorizeGuest(Order $order, string $token): void { abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token), 403); }
}
