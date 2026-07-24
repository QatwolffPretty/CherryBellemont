<?php
namespace App\Http\Controllers;
use App\Services\Cart;
use App\Services\CouponService;
use App\Services\GiftWrapping;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ShippingQuoteController extends Controller
{
    public function __invoke(Request $request, ShippingCalculator $calculator, Cart $cart, CouponService $coupons, GiftWrapping $giftWrapping): JsonResponse
    {
        $data = $request->validate([
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'delivery_method_id' => ['required', 'integer'],
            'gift_wrapping' => ['nullable', 'boolean'],
        ]);
        $shipping = $calculator->calculate($data['state'] ?? null, $data['city'] ?? null, $data['postcode'] ?? null, (int) $data['delivery_method_id']);
        $subtotal = $cart->totals($cart->lines())['subtotal'];
        $shippingCents = (int) round(((float) $shipping['shipping_fee']) * 100);
        $coupon = $coupons->calculate($cart->couponCode(), $subtotal, $shippingCents, $data['customer_email'] ?? null);
        $giftWrappingCents = $giftWrapping->feeCents($request->boolean('gift_wrapping'));

        return response()->json($shipping + [
            'coupon_code' => $coupon['coupon_code'],
            'discount_amount' => number_format($coupon['discount_cents'] / 100, 2, '.', ''),
            'original_shipping_fee' => number_format($coupon['original_shipping_cents'] / 100, 2, '.', ''),
            'free_shipping_discount' => number_format($coupon['free_shipping_discount_cents'] / 100, 2, '.', ''),
            'gift_wrapping_fee' => number_format($giftWrappingCents / 100, 2, '.', ''),
            'total' => number_format(($coupon['total_cents'] + $giftWrappingCents) / 100, 2, '.', ''),
        ]);
    }
}
