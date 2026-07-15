<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    public function index()
    {
        return view('admin.shipping-zones.index', ['zones' => ShippingZone::orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.shipping-zones.form', ['zone' => new ShippingZone]);
    }

    public function store(Request $request): RedirectResponse
    {
        ShippingZone::create($this->data($request));

        return to_route('admin.shipping-zones.index')->with('success', 'Zone saved.');
    }

    public function edit(ShippingZone $shippingZone)
    {
        return view('admin.shipping-zones.form', ['zone' => $shippingZone]);
    }

    public function update(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        $shippingZone->update($this->data($request));

        return to_route('admin.shipping-zones.index')->with('success', 'Zone updated.');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        if ($shippingZone->orders()->exists()) {
            return back()->withErrors(['shipping_zone' => 'This zone is referenced by existing orders and cannot be deleted.']);
        }

        $shippingZone->delete();

        return back()->with('success', 'Zone removed.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255', 'state' => 'required|string|max:120', 'city_or_area' => 'nullable|string|max:120',
            'postcode_from' => 'nullable|string|max:20', 'postcode_to' => 'nullable|string|max:20', 'base_fee' => 'required|numeric|min:0',
            'sort_order' => 'required|integer|min:0', 'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
