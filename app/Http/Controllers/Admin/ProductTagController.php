<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductCatalogueAttributeRequest;
use App\Models\ProductTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductTagController extends Controller
{
    public function index(Request $request): View
    {
        $tags = ProductTag::query()->withCount('products')->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))->ordered()->paginate(30)->withQueryString();
        return view('admin.catalogue-attributes.index', ['items' => $tags, 'type' => 'tag', 'title' => 'Collection tags', 'createRoute' => 'admin.product-tags.create', 'editRoute' => 'admin.product-tags.edit', 'destroyRoute' => 'admin.product-tags.destroy']);
    }

    public function create(): View { return view('admin.catalogue-attributes.form', ['item' => new ProductTag, 'type' => 'tag', 'title' => 'Add collection tag', 'route' => route('admin.product-tags.store')]); }
    public function store(ProductCatalogueAttributeRequest $request): RedirectResponse { ProductTag::query()->create($this->data($request)); return to_route('admin.product-tags.index')->with('success', 'Collection tag created.'); }
    public function edit(ProductTag $productTag): View { return view('admin.catalogue-attributes.form', ['item' => $productTag, 'type' => 'tag', 'title' => 'Edit collection tag', 'route' => route('admin.product-tags.update', $productTag)]); }
    public function update(ProductCatalogueAttributeRequest $request, ProductTag $productTag): RedirectResponse { $productTag->update($this->data($request)); return to_route('admin.product-tags.index')->with('success', 'Collection tag updated.'); }
    public function destroy(ProductTag $productTag): RedirectResponse
    {
        if ($productTag->products()->exists()) { $productTag->update(['is_active' => false]); return back()->with('success', 'Collection tag is assigned to products, so it was deactivated instead of deleted.'); }
        $productTag->delete(); return to_route('admin.product-tags.index')->with('success', 'Collection tag removed.');
    }

    private function data(ProductCatalogueAttributeRequest $request): array
    {
        $data = $request->validated();
        $data['slug'] = Str::slug((string) ($data['slug'] ?: $data['name']));
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
