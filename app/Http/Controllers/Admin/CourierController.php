<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourierRequest;
use App\Models\Courier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CourierController extends Controller
{
    public function index(Request $request): View
    {
        $couriers = Courier::query()
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn ($match) => $match->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.couriers.index', compact('couriers'));
    }

    public function create(): View
    {
        return view('admin.couriers.form', ['courier' => new Courier]);
    }

    public function store(CourierRequest $request): RedirectResponse
    {
        $courier = Courier::create($this->payload($request));

        return to_route('admin.couriers.edit', $courier)->with('success', 'Courier added.');
    }

    public function edit(Courier $courier): View
    {
        return view('admin.couriers.form', compact('courier'));
    }

    public function update(CourierRequest $request, Courier $courier): RedirectResponse
    {
        $courier->update($this->payload($request, $courier));

        return back()->with('success', 'Courier updated.');
    }

    /** @return array<string, mixed> */
    private function payload(CourierRequest $request, ?Courier $courier = null): array
    {
        $data = $request->safe()->except('logo');
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('logo')) {
            if ($courier?->logo_path) {
                Storage::disk('public')->delete($courier->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('couriers', 'public');
        }

        return $data;
    }
}
