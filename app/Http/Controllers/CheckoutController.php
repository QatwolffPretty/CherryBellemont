<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Order;
use App\Models\DeliveryMethod;
use App\Models\Product;
use App\Services\Cart;
use App\Services\ShippingCalculator;
use App\Services\OrderNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Cart $cart): View|RedirectResponse
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) return to_route('cart.index')->withErrors(['cart' => 'Your bag is empty.']);
        $deliveryMethods = DeliveryMethod::query()->where('is_active', true)->orderBy('sort_order')->get();
        if ($deliveryMethods->isEmpty()) {
            return to_route('cart.index')->withErrors(['cart' => 'Checkout is temporarily unavailable because no delivery methods are active.']);
        }
        return view('checkout.create', compact('lines','deliveryMethods') + $cart->totals($lines));
    }

    public function store(StoreCheckoutRequest $request, Cart $cart, ShippingCalculator $shippingCalculator, OrderNotifier $notifier): RedirectResponse
    {
        $contents = $cart->contents();
        if ($contents === []) return to_route('cart.index')->withErrors(['cart' => 'Your bag is empty.']);
        $data = $request->validated();

        $order = DB::transaction(function () use ($contents, $data, $request, $shippingCalculator): Order {
            $products = Product::query()->whereIn('id', array_keys($contents))->lockForUpdate()->get()->keyBy('id');
            $items = [];
            $subtotalCents = 0;
            foreach ($contents as $productId => $quantity) {
                $product = $products->get($productId);
                if (! $product || $product->status !== 'active') throw ValidationException::withMessages(['cart' => 'One of the pieces in your bag is no longer available.']);
                if ($quantity > $product->stock) throw ValidationException::withMessages(['cart' => $product->name.' has only '.$product->stock.' available.']);
                $unitPriceCents = (int) round(((float) $product->price) * 100);
                $lineTotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $lineTotalCents;
                $items[] = compact('product', 'quantity', 'unitPriceCents', 'lineTotalCents');
            }
            $shipping=$shippingCalculator->calculate($data['state'] ?? null,$data['city'] ?? null,$data['postcode'] ?? null,(int)$data['delivery_method_id']);
            $number = $this->orderNumber();
            $amount = fn (int $cents): string => number_format($cents / 100, 2, '.', '');
            $shippingCents=(int)round(((float)$shipping['shipping_fee'])*100);
            $shippingAddress = collect($data)->only(['customer_name', 'customer_email', 'customer_phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country'])->all();
            $order = Order::create(['user_id' => $request->user()?->id, 'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64), ...$shippingAddress, 'full_name' => $data['customer_name'], 'email' => $data['customer_email'], 'phone' => $data['customer_phone'], 'shipping_address' => $shippingAddress, 'customer_notes' => $data['customer_notes'] ?? null, 'delivery_instructions' => $data['delivery_instructions'] ?? null, 'shipping_zone_id'=>$shipping['shipping_zone_id'], 'delivery_method_id'=>$shipping['delivery_method_id'], 'shipping_method_name'=>$shipping['display_label'], 'shipping_fee'=>$amount($shippingCents), 'pickup_location'=>$shipping['pickup_location'], 'subtotal' => $amount($subtotalCents), 'total' => $amount($subtotalCents+$shippingCents), 'payment_method' => $data['payment_method'], 'payment_status' => 'pending', 'status' => 'pending', 'order_status' => 'pending']);
            foreach ($items as $item) {
                $product = $item['product'];
                $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => $item['quantity'], 'unit_price' => $amount($item['unitPriceCents']), 'total' => $amount($item['lineTotalCents']), 'line_total' => $amount($item['lineTotalCents'])]);
            }
            foreach ($items as $item) $item['product']->decrement('stock', $item['quantity']);
            return $order;
        });
        $notifier->send($order, 'order_placed');
        $cart->clear();
        return to_route('orders.guest.duitnow', ['order' => $order->order_number, 'token' => $order->guest_access_token])->with('success', 'Order '.$order->order_number.' has been created.');
    }

    private function orderNumber(): string
    {
        do { $number = 'CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)); } while (Order::query()->where('order_number', $number)->exists());
        return $number;
    }
}
