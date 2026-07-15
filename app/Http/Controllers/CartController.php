<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
        $quantity = $request->validate(['quantity' => ['nullable', 'integer', 'min:1']])['quantity'] ?? 1;
        $newQuantity = ($cart->contents()[$product->id] ?? 0) + $quantity;

        if ($newQuantity > $product->stock) {
            return back()->withErrors(['quantity' => 'Only '.$product->stock.' of this piece are available.'])->withInput();
        }

        $cart->put($product->id, $newQuantity);

        return to_route('cart.index')->with('success', 'Piece added to your bag.');
    }

    public function update(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:1']])['quantity'];
        abort_unless($product->status === 'active', 404);

        if ($quantity > $product->stock) {
            return back()->withErrors(['quantity' => 'Only '.$product->stock.' of this piece are available.']);
        }

        $cart->put($product->id, $quantity);

        return to_route('cart.index')->with('success', 'Bag updated.');
    }

    public function destroy(Product $product, Cart $cart): RedirectResponse
    {
        $cart->forget($product->id);

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

        if ($lines->isEmpty()) {
            return back()->withErrors(['coupon' => 'Add a piece to your bag before applying a coupon.']);
        }

        try {
            $summary = $coupons->calculate($data['coupon_code'], $cart->totals($lines)['subtotal'], 0);
            $cart->applyCoupon($summary['coupon_code']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable $exception) {
            Log::error('Coupon application failed unexpectedly.', [
                'coupon_code' => $coupons->normalize($data['coupon_code']),
                'exception' => $exception,
            ]);

            return back()->withErrors(['coupon' => 'Coupon could not be applied. Please try again.'])->withInput();
        }

        return back()->with('success', 'Coupon applied successfully.');
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
