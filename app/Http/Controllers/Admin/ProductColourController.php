<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductCatalogueAttributeRequest;
use App\Models\ProductColour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductColourController extends Controller
{
    public function index(Request $request): View
    {
        $colours = ProductColour::query()->withCount('products')->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))->ordered()->paginate(30)->withQueryString();
        return view('admin.catalogue-attributes.index', ['items' => $colours, 'type' => 'colour', 'title' => 'Colours', 'createRoute' => 'admin.product-colours.create', 'editRoute' => 'admin.product-colours.edit', 'destroyRoute' => 'admin.product-colours.destroy']);
    }

    public function create(): View { return view('admin.catalogue-attributes.form', ['item' => new ProductColour, 'type' => 'colour', 'title' => 'Add colour', 'route' => route('admin.product-colours.store')]); }
    public function store(ProductCatalogueAttributeRequest $request): RedirectResponse { ProductColour::query()->create($this->data($request)); return to_route('admin.product-colours.index')->with('success', 'Colour created.'); }
    public function edit(ProductColour $productColour): View { return view('admin.catalogue-attributes.form', ['item' => $productColour, 'type' => 'colour', 'title' => 'Edit colour', 'route' => route('admin.product-colours.update', $productColour)]); }
    public function update(ProductCatalogueAttributeRequest $request, ProductColour $productColour): RedirectResponse { $productColour->update($this->data($request)); return to_route('admin.product-colours.index')->with('success', 'Colour updated.'); }
    public function destroy(ProductColour $productColour): RedirectResponse
    {
        if ($productColour->products()->exists()) { $productColour->update(['is_active' => false]); return back()->with('success', 'Colour is assigned to products, so it was deactivated instead of deleted.'); }
        $productColour->delete(); return to_route('admin.product-colours.index')->with('success', 'Colour removed.');
    }

    private function data(ProductCatalogueAttributeRequest $request): array
    {
        $data = $request->validated();
        $data['slug'] = Str::slug((string) ($data['slug'] ?: $data['name']));
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
