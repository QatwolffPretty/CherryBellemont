<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeliveryMethodController extends Controller
{
    public function index()
    {
        return view('admin.delivery-methods.index', ['methods' => DeliveryMethod::orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('admin.delivery-methods.form', ['method' => new DeliveryMethod]);
    }

    public function store(Request $request): RedirectResponse
    {
        DeliveryMethod::create($this->data($request));

        return to_route('admin.delivery-methods.index')->with('success', 'Method saved.');
    }

    public function edit(DeliveryMethod $deliveryMethod)
    {
        return view('admin.delivery-methods.form', ['method' => $deliveryMethod]);
    }

    public function update(Request $request, DeliveryMethod $deliveryMethod): RedirectResponse
    {
        $deliveryMethod->update($this->data($request));

        return to_route('admin.delivery-methods.index')->with('success', 'Method updated.');
    }

    public function destroy(DeliveryMethod $deliveryMethod): RedirectResponse
    {
        if ($deliveryMethod->orders()->exists()) {
            return back()->withErrors(['delivery_method' => 'This method is referenced by existing orders and cannot be deleted.']);
        }

        $deliveryMethod->delete();

        return back()->with('success', 'Method removed.');
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255', 'code' => 'required|alpha_dash|max:80|unique:delivery_methods,code,'.($request->route('delivery_method')?->id ?? 'NULL'),
            'description' => 'nullable|string', 'additional_fee' => 'required|numeric|min:0', 'estimated_days' => 'nullable|integer|min:0',
            'sort_order' => 'required|integer|min:0', 'is_active' => 'nullable|boolean', 'is_pickup' => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active'), 'is_pickup' => $request->boolean('is_pickup')];
    }
}
