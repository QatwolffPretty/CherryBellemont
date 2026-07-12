<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderFulfilmentRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()->latest();
        if ($search = $request->string('search')->trim()->value()) $orders->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_email', 'like', "%{$search}%")->orWhere('customer_phone', 'like', "%{$search}%"));
        foreach (['payment_status', 'order_status', 'delivery_method_id'] as $filter) if ($request->filled($filter)) $orders->where($filter, $request->$filter);
        return view('admin.orders.index', ['orders' => $orders->paginate(20)->withQueryString()]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load('items.product', 'paymentReceipts.reviewer')]);
    }

    public function update(UpdateOrderFulfilmentRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $order): void {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $this->ensureTransition($order, $data['order_status']);

            if ($data['order_status'] === 'cancelled' && ! $order->stock_restored_at) {
                foreach ($order->items as $item) if ($item->product_id && ($product = Product::lockForUpdate()->find($item->product_id))) $product->increment('stock', $item->quantity);
                $data['stock_restored_at'] = now();
                $data['cancelled_at'] = now();
            }
            if ($data['order_status'] === 'shipped' && ! $order->shipped_at) $data['shipped_at'] = now();
            if ($data['order_status'] === 'delivered' && ! $order->delivered_at) $data['delivered_at'] = now();
            $order->update($data);
        });
        return back()->with('success', 'Order fulfilment updated.');
    }

    private function ensureTransition(Order $order, string $target): void
    {
        if ($order->order_status === $target) return;
        if ($order->order_status === 'cancelled') throw ValidationException::withMessages(['order_status' => 'Cancelled orders cannot be reopened.']);
        if ($order->payment_status !== 'paid' && ! in_array($target, ['pending', 'payment_review', 'cancelled'], true)) throw ValidationException::withMessages(['order_status' => 'Payment must be approved before fulfilment.']);
        $allowed = ['pending' => ['payment_review', 'paid', 'cancelled'], 'payment_review' => ['paid', 'cancelled'], 'paid' => ['processing', 'cancelled'], 'processing' => ['packed', 'cancelled'], 'packed' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => []];
        if (! in_array($target, $allowed[$order->order_status] ?? [], true)) throw ValidationException::withMessages(['order_status' => 'This order status change is not allowed.']);
    }
}
