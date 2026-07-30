<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Order;
use App\Models\DeliveryMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\CouponService;
use App\Services\GiftWrapping;
use App\Services\ShippingCalculator;
use App\Services\OrderNotifier;
use App\Services\AdminNotificationService;
use App\Services\StripeCheckoutService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Cart $cart, CouponService $coupons, SettingsService $settings, GiftWrapping $giftWrapping): View|RedirectResponse
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) return to_route('cart.index')->withErrors(['cart' => 'Your bag is empty.']);
        $deliveryMethods = DeliveryMethod::query()->where('is_active', true)
            ->when(! $settings->get('shipping.self_pickup_enabled', true), fn ($query) => $query->where('is_pickup', false))
            ->orderBy('sort_order')->get();
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

        $paymentOptions = [
            'duitnow' => ['enabled' => (bool) $settings->get('payment.duitnow_enabled', true), 'label' => $settings->get('payment.duitnow_display_name', 'DuitNow manual payment')],
            'stripe' => ['enabled' => (bool) $settings->get('payment.stripe_enabled', true), 'label' => $settings->get('payment.stripe_display_name', 'Card Payment by Stripe')],
        ];

        return view('checkout.create', compact('lines', 'deliveryMethods', 'pendingStripeOrder', 'couponSummary', 'couponMessage', 'paymentOptions', 'giftWrapping') + $totals);
    }

    public function store(StoreCheckoutRequest $request, Cart $cart, ShippingCalculator $shippingCalculator, CouponService $coupons, GiftWrapping $giftWrapping, OrderNotifier $notifier, AdminNotificationService $adminNotifier, StripeCheckoutService $stripe, SettingsService $settings): RedirectResponse
    {
        $contents = $cart->contents();
        if ($contents === []) return to_route('cart.index')->withErrors(['cart' => 'Your bag is empty.']);
        if (session()->has('stripe_pending_order')) {
            return to_route('checkout.create')->withErrors([
                'stripe' => 'Please retry the existing Stripe payment before placing another order.',
            ]);
        }
        $data = $request->validated();
        if (! (bool) $settings->get('payment.'.$data['payment_method'].'_enabled', true)) {
            throw ValidationException::withMessages(['payment_method' => 'This payment method is temporarily unavailable.']);
        }

        $couponCode = $cart->couponCode();
        $inventoryAlerts = [];
        $lowStockThreshold = max(0, (int) $settings->get('inventory.low_stock_threshold', config('store.low_stock_threshold', 3)));
        $order = DB::transaction(function () use ($contents, $data, $request, $shippingCalculator, $coupons, $giftWrapping, $couponCode, &$inventoryAlerts, $lowStockThreshold): Order {
            $productIds = collect($contents)->pluck('product_id')->unique()->values()->all();
            $products = Product::query()->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::query()
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->with(['size:id,name', 'colour:id,name'])
                ->get();
            $variantsById = $variants->keyBy('id');
            $variantProductIds = $variants->pluck('product_id')->flip();
            $items = [];
            $subtotalCents = 0;
            foreach ($contents as $cartLine) {
                $product = $products->get($cartLine['product_id']);
                $quantity = $cartLine['quantity'];
                if (! $product || $product->status !== 'active') throw ValidationException::withMessages(['cart' => 'One of the pieces in your bag is no longer available.']);
                $variant = $cartLine['variant_id'] ? $variantsById->get($cartLine['variant_id']) : null;
                $isVariantProduct = $variantProductIds->has($product->id);
                if ($isVariantProduct && ! $variant) {
                    throw ValidationException::withMessages(['cart' => 'Please choose the required options for '.$product->name.'.']);
                }
                if ($variant && ($variant->product_id !== $product->id || ! $variant->is_active)) {
                    throw ValidationException::withMessages(['cart' => 'A selected product option is no longer available.']);
                }
                $availableStock = $variant ? (int) $variant->stock : (int) $product->stock;
                if ($quantity > $availableStock) throw ValidationException::withMessages(['cart' => $product->name.' has only '.$availableStock.' of this option available.']);
                $unitPriceCents = (int) round(((float) ($variant?->price_override ?? $product->price)) * 100);
                $lineTotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $lineTotalCents;
                $items[] = compact('product', 'variant', 'quantity', 'unitPriceCents', 'lineTotalCents');
            }
            $shipping=$shippingCalculator->calculate($data['state'] ?? null,$data['city'] ?? null,$data['postcode'] ?? null,(int)$data['delivery_method_id']);
            $number = $this->orderNumber();
            $amount = fn (int $cents): string => number_format($cents / 100, 2, '.', '');
            $shippingCents=(int)round(((float)$shipping['shipping_fee'])*100);
            $coupon = $coupons->calculate($couponCode, $subtotalCents, $shippingCents, $data['customer_email'], true);
            $giftWrappingSelected = $giftWrapping->enabled() && $request->boolean('gift_wrapping');
            $giftWrappingCents = $giftWrapping->feeCents($giftWrappingSelected);
            $totalCents = $coupon['total_cents'] + $giftWrappingCents;
            $shippingAddress = collect($data)->only(['customer_name', 'customer_email', 'customer_phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country'])->all();
            $order = Order::create(['user_id' => $request->user()?->id, 'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64), ...$shippingAddress, 'full_name' => $data['customer_name'], 'email' => $data['customer_email'], 'phone' => $data['customer_phone'], 'shipping_address' => $shippingAddress, 'customer_notes' => $data['customer_notes'] ?? null, 'delivery_instructions' => $data['delivery_instructions'] ?? null, 'shipping_zone_id'=>$shipping['shipping_zone_id'], 'delivery_method_id'=>$shipping['delivery_method_id'], 'shipping_method_name'=>$shipping['display_label'], 'shipping_fee'=>$amount($coupon['original_shipping_cents']), 'original_shipping_fee'=>$amount($coupon['original_shipping_cents']), 'free_shipping_discount'=>$amount($coupon['free_shipping_discount_cents']), 'coupon_id'=>$coupon['coupon']?->id, 'coupon_code'=>$coupon['coupon_code'], 'discount_amount'=>$amount($coupon['discount_cents']), 'gift_wrapping'=>$giftWrappingSelected, 'gift_wrapping_fee'=>$amount($giftWrappingCents), 'gift_message'=>$giftWrappingSelected ? ($data['gift_message'] ?? null) : null, 'pickup_location'=>$shipping['pickup_location'], 'subtotal' => $amount($subtotalCents), 'total' => $amount($totalCents), 'payment_method' => $data['payment_method'], 'payment_provider' => $data['payment_method'], 'payment_status' => 'pending', 'status' => 'pending', 'order_status' => 'pending']);
            foreach ($items as $item) {
                $product = $item['product'];
                $variant = $item['variant'];
                $variantDescription = collect([$variant?->colour?->name, $variant?->size?->name])->filter()->implode(' / ') ?: null;
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'name' => $product->name,
                    'product_name' => $product->name,
                    'sku' => $variant?->sku,
                    'size_name' => $variant?->size?->name,
                    'colour_name' => $variant?->colour?->name,
                    'variant_description' => $variantDescription,
                    'quantity' => $item['quantity'],
                    'unit_price' => $amount($item['unitPriceCents']),
                    'unit_cost' => $product->cost_price,
                    'total' => $amount($item['lineTotalCents']),
                    'line_total' => $amount($item['lineTotalCents']),
                ]);
            }
            if ($coupon['coupon']) {
                $coupons->recordUsage($coupon['coupon'], $order, $data['customer_email'], $coupon['discount_cents']);
            }
            foreach (collect($items)->groupBy(fn (array $item) => $item['product']->id) as $productId => $productItems) {
                /** @var Product $product */
                $product = $products->get((int) $productId);
                $previousStock = (int) $product->stock;
                $quantity = (int) $productItems->sum('quantity');

                foreach ($productItems as $item) {
                    if ($item['variant']) {
                        $item['variant']->decrement('stock', $item['quantity']);
                    }
                }

                $product->decrement('stock', $quantity);
                $remainingStock = (int) $product->fresh()->stock;

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
                    'exception_class' => $exception::class,
                ]);

                $isRepeatedFailure = filled($order->stripe_failure_reason);
                try {
                    $stripe->recordCheckoutFailure($order);
                } catch (\Throwable $recordException) {
                    Log::error('Unable to record a Stripe Checkout initialization failure.', [
                        'order_number' => $order->order_number,
                        'exception_class' => $recordException::class,
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
