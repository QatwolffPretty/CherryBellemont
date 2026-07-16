<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Order;
use App\Models\DeliveryMethod;
use App\Models\Product;
use App\Services\Cart;
use App\Services\CouponService;
use App\Services\ShippingCalculator;
use App\Services\OrderNotifier;
use App\Services\AdminNotificationService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Cart $cart, CouponService $coupons): View|RedirectResponse
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) return to_route('cart.index')->withErrors(['cart' => 'Your bag is empty.']);
        $deliveryMethods = DeliveryMethod::query()->where('is_active', true)->orderBy('sort_order')->get();
        if ($deliveryMethods->isEmpty()) {
            return to_route('cart.index')->withErrors(['cart' => 'Checkout is temporarily unavailable because no delivery methods are active.']);
        }
        $pendingStripeOrder = session('stripe_pending_order');
        $totals = $cart->totals($lines);
        $couponSummary = $coupons->emptyResult($totals['subtotal'], 0);
        $couponMessage = null;

        if ($cart->couponCode()) {
            try {
                $couponSummary = $coupons->calculate($cart->couponCode(), $totals['subtotal'], 0, old('customer_email', auth()->user()?->email));
            } catch (ValidationException $exception) {
                $cart->removeCoupon();
                $couponMessage = (string) (collect($exception->errors())->flatten()->first() ?? 'Coupon is no longer available.');
            }
        }

        return view('checkout.create', compact('lines', 'deliveryMethods', 'pendingStripeOrder', 'couponSummary', 'couponMessage') + $totals);
    }

    public function store(StoreCheckoutRequest $request, Cart $cart, ShippingCalculator $shippingCalculator, CouponService $coupons, OrderNotifier $notifier, AdminNotificationService $adminNotifier, StripeCheckoutService $stripe): RedirectResponse
    {
        $contents = $cart->contents();
        if ($contents === []) return to_route('cart.index')->withErrors(['cart' => 'Your bag is empty.']);
        if (session()->has('stripe_pending_order')) {
            return to_route('checkout.create')->withErrors([
                'stripe' => 'Please retry the existing Stripe payment before placing another order.',
            ]);
        }
        $data = $request->validated();

        $couponCode = $cart->couponCode();
        $inventoryAlerts = [];
        $lowStockThreshold = (int) config('store.low_stock_threshold', 3);
        $order = DB::transaction(function () use ($contents, $data, $request, $shippingCalculator, $coupons, $couponCode, &$inventoryAlerts, $lowStockThreshold): Order {
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
            $coupon = $coupons->calculate($couponCode, $subtotalCents, $shippingCents, $data['customer_email'], true);
            $shippingAddress = collect($data)->only(['customer_name', 'customer_email', 'customer_phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country'])->all();
            $order = Order::create(['user_id' => $request->user()?->id, 'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64), ...$shippingAddress, 'full_name' => $data['customer_name'], 'email' => $data['customer_email'], 'phone' => $data['customer_phone'], 'shipping_address' => $shippingAddress, 'customer_notes' => $data['customer_notes'] ?? null, 'delivery_instructions' => $data['delivery_instructions'] ?? null, 'shipping_zone_id'=>$shipping['shipping_zone_id'], 'delivery_method_id'=>$shipping['delivery_method_id'], 'shipping_method_name'=>$shipping['display_label'], 'shipping_fee'=>$amount($coupon['original_shipping_cents']), 'original_shipping_fee'=>$amount($coupon['original_shipping_cents']), 'free_shipping_discount'=>$amount($coupon['free_shipping_discount_cents']), 'coupon_id'=>$coupon['coupon']?->id, 'coupon_code'=>$coupon['coupon_code'], 'discount_amount'=>$amount($coupon['discount_cents']), 'pickup_location'=>$shipping['pickup_location'], 'subtotal' => $amount($subtotalCents), 'total' => $amount($coupon['total_cents']), 'payment_method' => $data['payment_method'], 'payment_provider' => $data['payment_method'], 'payment_status' => 'pending', 'status' => 'pending', 'order_status' => 'pending']);
            foreach ($items as $item) {
                $product = $item['product'];
                $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => $item['quantity'], 'unit_price' => $amount($item['unitPriceCents']), 'total' => $amount($item['lineTotalCents']), 'line_total' => $amount($item['lineTotalCents'])]);
            }
            if ($coupon['coupon']) {
                $coupons->recordUsage($coupon['coupon'], $order, $data['customer_email'], $coupon['discount_cents']);
            }
            foreach ($items as $item) {
                $product = $item['product'];
                $previousStock = (int) $product->stock;
                $remainingStock = $previousStock - $item['quantity'];
                $product->decrement('stock', $item['quantity']);

                if ($previousStock > $lowStockThreshold && $remainingStock <= $lowStockThreshold && $remainingStock > 0) {
                    $inventoryAlerts[] = ['event' => 'low_stock', 'product' => $product->fresh()];
                }
                if ($previousStock > 0 && $remainingStock === 0) {
                    $inventoryAlerts[] = ['event' => 'out_of_stock', 'product' => $product->fresh()];
                }
            }
            return $order;
        });
        $notifier->send($order, 'order_placed');
        $adminNotifier->send('new_order', ['order' => $order->loadMissing('items')]);
        foreach ($inventoryAlerts as $alert) {
            $adminNotifier->send($alert['event'], ['product' => $alert['product']]);
        }
        if ($order->payment_method === 'stripe') {
            try {
                $session = $stripe->beginCheckout($order);

                $cart->clear();
                session()->forget('stripe_pending_order');

                return redirect()->away($session->url);
            } catch (\Throwable $exception) {
                Log::error('Unable to start Stripe Checkout.', [
                    'order_number' => $order->order_number,
                    'exception' => $exception,
                ]);

                $isRepeatedFailure = filled($order->stripe_failure_reason);
                try {
                    $stripe->recordCheckoutFailure($order);
                } catch (\Throwable $recordException) {
                    Log::error('Unable to record a Stripe Checkout initialization failure.', [
                        'order_number' => $order->order_number,
                        'exception' => $recordException,
                    ]);
                }

                if ($isRepeatedFailure) {
                    $adminNotifier->send('payment_attention', [
                        'order' => $order,
                        'provider' => 'Stripe',
                        'summary' => 'Stripe Checkout could not be initialized again for this order.',
                        'reference' => $order->stripe_checkout_session_id,
                        'occurredAt' => now(),
                    ]);
                }

                session()->put('stripe_pending_order', [
                    'order' => $order->order_number,
                    'token' => $order->guest_access_token,
                ]);

                return to_route('checkout.create')
                    ->withErrors(['stripe' => 'Stripe Checkout could not be started. Please try again.']);
            }
        }
        $cart->clear();
        return to_route('orders.guest.duitnow', ['order' => $order->order_number, 'token' => $order->guest_access_token])->with('success', 'Order '.$order->order_number.' has been created.');
    }

    private function orderNumber(): string
    {
        do { $number = 'CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)); } while (Order::query()->where('order_number', $number)->exists());
        return $number;
    }
}
