<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Calculate a coupon from trusted integer-sen amounts. Call this inside the
     * checkout transaction with $lockForUpdate enabled before recording usage.
     *
     * @return array{coupon: ?Coupon, coupon_code: ?string, discount_cents: int, original_shipping_cents: int, free_shipping_discount_cents: int, total_cents: int}
     */
    public function calculate(?string $code, int $subtotalCents, int $shippingCents, ?string $customerEmail = null, bool $lockForUpdate = false): array
    {
        $subtotalCents = max(0, $subtotalCents);
        $shippingCents = max(0, $shippingCents);
        $code = $this->normalize($code);

        if (! $code) {
            return $this->emptyResult($subtotalCents, $shippingCents);
        }

        $query = Coupon::query()->matchingCode($code);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();
        if (! $coupon) {
            $this->fail('Coupon does not exist.', $code);
        }

        $this->ensureEligible($coupon, $subtotalCents, $customerEmail);

        $discountCents = $coupon->type === 'percentage'
            ? (int) round($subtotalCents * ((float) $coupon->value / 100))
            : $this->toCents($coupon->value);

        if ($coupon->type === 'percentage' && $coupon->maximum_discount_amount !== null) {
            $discountCents = min($discountCents, $this->toCents($coupon->maximum_discount_amount));
        }

        $discountCents = min($subtotalCents, max(0, $discountCents));
        $freeShippingDiscountCents = $coupon->free_shipping ? $shippingCents : 0;

        return [
            'coupon' => $coupon,
            'coupon_code' => $coupon->code,
            'discount_cents' => $discountCents,
            'original_shipping_cents' => $shippingCents,
            'free_shipping_discount_cents' => $freeShippingDiscountCents,
            'total_cents' => max(0, $subtotalCents - $discountCents + $shippingCents - $freeShippingDiscountCents),
        ];
    }

    public function recordUsage(Coupon $coupon, Order $order, string $customerEmail, int $discountCents): void
    {
        if (CouponUsage::query()->where('order_id', $order->id)->exists()) {
            return;
        }

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'customer_email' => mb_strtolower(trim($customerEmail)),
            'discount_amount' => $this->decimal($discountCents),
            'used_at' => now(),
        ]);

        $coupon->update(['used_count' => $coupon->usages()->count()]);
    }

    public function normalize(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }

    /** @return array{coupon: null, coupon_code: null, discount_cents: int, original_shipping_cents: int, free_shipping_discount_cents: int, total_cents: int} */
    public function emptyResult(int $subtotalCents, int $shippingCents): array
    {
        return [
            'coupon' => null,
            'coupon_code' => null,
            'discount_cents' => 0,
            'original_shipping_cents' => $shippingCents,
            'free_shipping_discount_cents' => 0,
            'total_cents' => $subtotalCents + $shippingCents,
        ];
    }

    private function ensureEligible(Coupon $coupon, int $subtotalCents, ?string $customerEmail): void
    {
        if (! $coupon->is_active) {
            $this->fail('Coupon is inactive.', $coupon->code);
        }
        if ($coupon->starts_at?->isFuture()) {
            $this->fail('Coupon is not active yet.', $coupon->code);
        }
        if ($coupon->expires_at?->lessThanOrEqualTo(now())) {
            $this->fail('Coupon has expired.', $coupon->code);
        }
        if ($coupon->minimum_order_amount !== null && $subtotalCents < $this->toCents($coupon->minimum_order_amount)) {
            $this->fail('Minimum purchase not reached for this coupon.', $coupon->code);
        }

        $usageCount = $coupon->usages()->count();
        if ($coupon->usage_limit !== null && $usageCount >= $coupon->usage_limit) {
            $this->fail('Coupon usage limit reached.', $coupon->code);
        }

        if ($customerEmail && $coupon->usage_limit_per_email !== null) {
            $emailUsageCount = $coupon->usages()
                ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($customerEmail))])
                ->count();

            if ($emailUsageCount >= $coupon->usage_limit_per_email) {
                $this->fail('Coupon usage limit reached for this email address.', $coupon->code);
            }
        }
    }

    private function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function fail(string $message, ?string $code = null): never
    {
        Log::notice('Coupon validation failed.', [
            'coupon_code' => $code,
            'reason' => $message,
        ]);

        throw ValidationException::withMessages(['coupon' => $message]);
    }
}
