<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderDocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('orders.index', ['orders' => $request->user()->orders()->latest()->paginate(12)]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        return view('orders.show', ['order' => $order->load('items.product', 'items.review', 'paymentReceipts')]);
    }

    public function confirmation(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        return view('orders.confirmation', ['order' => $order->load('items')]);
    }

    public function guestShow(Order $order, string $token): View
    {
        $this->authorizeGuest($order, $token);

        return view('orders.show', ['order' => $order->load('items.product', 'items.review', 'paymentReceipts'), 'token' => $token]);
    }

    public function guestConfirmation(Order $order, string $token): View
    {
        $this->authorizeGuest($order, $token);

        return view('orders.confirmation', ['order' => $order->load('items'), 'token' => $token]);
    }

    public function guestDuitNowInstructions(Order $order, string $token): View
    {
        $this->authorizeGuest($order, $token);
        abort_unless($order->payment_method === 'duitnow', 404);

        return view('orders.duitnow', compact('order', 'token'));
    }

    public function invoice(Request $request, Order $order, OrderDocumentService $documents): Response
    {
        $this->authorizeOrder($request, $order);

        return $this->downloadInvoice($order, $documents);
    }

    public function guestInvoice(Order $order, string $token, OrderDocumentService $documents): Response
    {
        $this->authorizeGuest($order, $token);

        return $this->downloadInvoice($order, $documents);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }

    private function authorizeGuest(Order $order, string $token): void
    {
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token), 403);
    }

    private function downloadInvoice(Order $order, OrderDocumentService $documents): Response
    {
        abort_unless($order->payment_status === 'paid', 403);

        return $documents->invoice($order)->download('invoice-'.($order->order_number ?? $order->number).'.pdf');
    }
}
