<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $coupons = Coupon::query()->withCount('usages')->latest();

        match ($request->string('status')->value()) {
            'active' => $coupons->where('is_active', true)->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'inactive' => $coupons->where('is_active', false),
            'expired' => $coupons->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            'scheduled' => $coupons->whereNotNull('starts_at')->where('starts_at', '>', now()),
            default => null,
        };

        return view('admin.coupons.index', ['coupons' => $coupons->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon]);
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        Coupon::create($this->data($request));

        return to_route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', compact('coupon'));
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->data($request));

        return to_route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->usages()->exists()) {
            return back()->withErrors(['coupon' => 'This coupon has been used and cannot be deleted. Deactivate it instead.']);
        }

        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    private function data(CouponRequest $request): array
    {
        return [
            ...$request->validated(),
            'code' => strtoupper(trim($request->string('code')->value())),
            'is_active' => $request->boolean('is_active'),
            'free_shipping' => $request->boolean('free_shipping'),
        ];
    }
}
