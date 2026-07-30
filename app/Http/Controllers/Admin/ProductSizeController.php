<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductCatalogueAttributeRequest;
use App\Models\ProductSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductSizeController extends Controller
{
    public function index(Request $request): View
    {
        $sizes = ProductSize::query()->withCount('products')->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))->ordered()->paginate(30)->withQueryString();
        return view('admin.catalogue-attributes.index', ['items' => $sizes, 'type' => 'size', 'title' => 'Sizes', 'createRoute' => 'admin.product-sizes.create', 'editRoute' => 'admin.product-sizes.edit', 'destroyRoute' => 'admin.product-sizes.destroy']);
    }

    public function create(): View { return view('admin.catalogue-attributes.form', ['item' => new ProductSize, 'type' => 'size', 'title' => 'Add size', 'route' => route('admin.product-sizes.store')]); }
    public function store(ProductCatalogueAttributeRequest $request): RedirectResponse { ProductSize::query()->create($this->data($request)); return to_route('admin.product-sizes.index')->with('success', 'Size created.'); }
    public function edit(ProductSize $productSize): View { return view('admin.catalogue-attributes.form', ['item' => $productSize, 'type' => 'size', 'title' => 'Edit size', 'route' => route('admin.product-sizes.update', $productSize)]); }
    public function update(ProductCatalogueAttributeRequest $request, ProductSize $productSize): RedirectResponse { $productSize->update($this->data($request)); return to_route('admin.product-sizes.index')->with('success', 'Size updated.'); }
    public function destroy(ProductSize $productSize): RedirectResponse { return $this->remove($productSize, 'admin.product-sizes.index'); }

    private function data(ProductCatalogueAttributeRequest $request): array
    {
        $data = $request->validated();
        $data['code'] = Str::upper(trim((string) ($data['code'] ?: $data['name'])));
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    private function remove(ProductSize $item, string $route): RedirectResponse
    {
        if ($item->products()->exists()) { $item->update(['is_active' => false]); return back()->with('success', 'Size is assigned to products, so it was deactivated instead of deleted.'); }
        $item->delete(); return to_route($route)->with('success', 'Size removed.');
    }
}
