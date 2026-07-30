<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Cart $cart, CouponService $coupons): View
    {
        $lines = $cart->lines();
        $totals = $cart->totals($lines);
        $couponSummary = $coupons->emptyResult($totals['subtotal'], 0);
        $couponMessage = null;

        if ($cart->couponCode()) {
            try {
                $couponSummary = $coupons->calculate($cart->couponCode(), $totals['subtotal'], 0);
            } catch (ValidationException $exception) {
                $cart->removeCoupon();
                $couponMessage = $this->couponMessage($exception);
            }
        }

        return view('cart.index', compact('lines', 'couponSummary', 'couponMessage') + $totals);
    }

    public function store(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer'],
            'size_id' => ['nullable', 'integer'],
            'colour_id' => ['nullable', 'integer'],
        ]);
        $quantity = $data['quantity'] ?? 1;
        $hasVariants = $product->variants()->exists();
        $variant = null;

        if ($hasVariants) {
            $requiresSize = $product->variants()->whereNotNull('product_size_id')->exists();
            $requiresColour = $product->variants()->whereNotNull('product_colour_id')->exists();
            $selectionLabel = match (true) {
                $requiresColour && $requiresSize => 'size and colour',
                $requiresColour => 'colour',
                default => 'size',
            };

            if (blank($data['variant_id'] ?? null)) {
                return back()->withErrors(['variant' => 'Please choose a '.$selectionLabel.' before adding this piece.'])->withInput();
            }

            $variant = ProductVariant::query()
                ->with(['size:id,name', 'colour:id,name'])
                ->where('product_id', $product->id)
                ->find($data['variant_id']);
            if (! $variant || ! $variant->is_active) {
                return back()->withErrors(['variant' => 'That product option is no longer available.'])->withInput();
            }
            if ($variant->product_size_id && (int) ($data['size_id'] ?? 0) !== $variant->product_size_id) {
                return back()->withErrors(['size' => 'Please select a valid size.'])->withInput();
            }
            if ($variant->product_colour_id && (int) ($data['colour_id'] ?? 0) !== $variant->product_colour_id) {
                return back()->withErrors(['colour' => 'Please select a valid colour.'])->withInput();
            }
        } elseif (filled($data['variant_id'] ?? null)) {
            return back()->withErrors(['variant' => 'This piece does not use selectable options.'])->withInput();
        }

        $cartKey = $cart->key($product->id, $variant?->id);
        $newQuantity = (int) ($cart->line($cartKey)['quantity'] ?? 0) + $quantity;
        $availableStock = $variant ? (int) $variant->stock : (int) $product->stock;

        if ($newQuantity > $availableStock) {
            return back()->withErrors(['quantity' => 'Only '.$availableStock.' of this option are available.'])->withInput();
        }

        $cart->put($product->id, $newQuantity, $variant?->id);

        return to_route('cart.index')->with('success', 'Piece added to your bag.');
    }

    public function update(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1'], 'cart_key' => ['nullable', 'string', 'max:120']]);
        $quantity = $data['quantity'];
        abort_unless($product->status === 'active', 404);
        $cartKey = $data['cart_key'] ?? $cart->key($product->id);
        $line = $cart->line($cartKey);
        abort_unless($line && $line['product_id'] === $product->id, 404);
        $variant = $line['variant_id'] ? ProductVariant::query()->where('product_id', $product->id)->find($line['variant_id']) : null;
        if ($line['variant_id'] && (! $variant || ! $variant->is_active)) {
            return back()->withErrors(['quantity' => 'This product option is no longer available.']);
        }
        $availableStock = $variant ? (int) $variant->stock : (int) $product->stock;

        if ($quantity > $availableStock) {
            return back()->withErrors(['quantity' => 'Only '.$availableStock.' of this option are available.']);
        }

        $cart->putByKey($cartKey, $quantity);

        return to_route('cart.index')->with('success', 'Bag updated.');
    }

    public function destroy(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['cart_key' => ['nullable', 'string', 'max:120']]);
        $cartKey = $data['cart_key'] ?? $cart->key($product->id);
        $line = $cart->line($cartKey);
        abort_unless($line && $line['product_id'] === $product->id, 404);
        $cart->forgetByKey($cartKey);

        return to_route('cart.index')->with('success', 'Piece removed from your bag.');
    }

    public function clear(Cart $cart): RedirectResponse
    {
        $cart->clear();

        return to_route('cart.index')->with('success', 'Your bag has been cleared.');
    }

    public function applyCoupon(Request $request, Cart $cart, CouponService $coupons): RedirectResponse
    {
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:64']]);
        $lines = $cart->lines();
        $normalizedCode = $coupons->normalize($data['coupon_code']);
        $subtotalCents = $cart->totals($lines)['subtotal'];

        Log::debug('Coupon application requested.', [
            'submitted_coupon_code' => $data['coupon_code'],
            'normalized_coupon_code' => $normalizedCode,
            'cart_subtotal_cents' => $subtotalCents,
            'session_coupon_code' => $cart->couponCode(),
        ]);

        if ($lines->isEmpty()) {
            return back()->withErrors(['coupon' => 'Add a piece to your bag before applying a coupon.']);
        }

        try {
            $summary = $coupons->calculate($normalizedCode, $subtotalCents, 0);
            $cart->applyCoupon($summary['coupon_code']);

            Log::debug('Coupon applied to cart.', [
                'normalized_coupon_code' => $normalizedCode,
                'coupon_found' => true,
                'cart_subtotal_cents' => $subtotalCents,
                'discount_cents' => $summary['discount_cents'],
                'final_total_cents' => $summary['total_cents'],
                'session_coupon_code' => $cart->couponCode(),
            ]);
        } catch (ValidationException $exception) {
            Log::info('Coupon application was rejected.', [
                'normalized_coupon_code' => $normalizedCode,
                'cart_subtotal_cents' => $subtotalCents,
                'session_coupon_code' => $cart->couponCode(),
                'reason' => $this->couponMessage($exception),
            ]);

            return back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable $exception) {
            Log::error('Coupon application failed unexpectedly.', [
                'coupon_code' => $normalizedCode,
                'exception_class' => $exception::class,
            ]);

            return back()->withErrors(['coupon' => 'Coupon could not be applied. Please try again.'])->withInput();
        }

        return back()->with('success', 'Coupon '.$summary['coupon_code'].' applied successfully.');
    }

    public function removeCoupon(Cart $cart): RedirectResponse
    {
        $cart->removeCoupon();

        return back()->with('success', 'Coupon removed.');
    }

    private function couponMessage(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? 'Coupon is no longer available.');
    }
}
