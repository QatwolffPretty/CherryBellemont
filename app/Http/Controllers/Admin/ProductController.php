<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Services\ProductGalleryService;
use App\Services\ProductStockNotificationService;
use App\Services\ProductVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()->with('primaryCategory:id,name')->latest();

        if ($request->boolean('low_stock')) {
            $products->where('stock', '<=', 5);
        }

        return view('admin.products.index', ['products' => $products->paginate(20)->withQueryString()]);
    }
    public function create(): View { return view('admin.products.form', array_merge(['product' => new Product], $this->catalogueOptions())); }
    public function store(ProductRequest $request, ProductGalleryService $gallery, ProductVariantService $variants): RedirectResponse
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request, $gallery, $variants): Product {
            $product = Product::query()->create($data);
            $this->syncCatalogueAssignments($product, $request);
            $variants->sync($product, $request->input('variants', []));
            $gallery->addUploads($product, $this->uploads($request), $request->input('image_alt_texts', []));
            return $product;
        });

        return to_route('admin.products.index')->with('success', 'Piece added to the collection.');
    }
    public function edit(Product $product): View
    {
        $product->load(['categories:id,name', 'sizes:id,name', 'colours:id,name', 'tags:id,name', 'primaryCategory:id,name', 'productImages', 'variants.size', 'variants.colour'])->loadCount([
            'stockNotifications as waiting_stock_notifications_count' => fn ($query) => $query->waiting(),
            'stockNotifications as notified_stock_notifications_count' => fn ($query) => $query->notified(),
        ]);

        return view('admin.products.form', array_merge(compact('product'), $this->catalogueOptions()));
    }
    public function update(ProductRequest $request, Product $product, ProductStockNotificationService $stockNotifications, ProductGalleryService $gallery, ProductVariantService $variants): RedirectResponse
    {
        $data = $this->validated($request);
        $previousStock = (int) $product->stock;

        DB::transaction(function () use ($product, $data, $request, $gallery, $variants): void {
            $product->update($data);
            $this->syncCatalogueAssignments($product, $request);
            $variants->sync($product, $request->input('variants', []));
            $gallery->addUploads($product, $this->uploads($request), $request->input('image_alt_texts', []));
        });
        $stockNotifications->handleStockChange($product->fresh(), $previousStock);

        return to_route('admin.products.index')->with('success', 'Piece updated.');
    }

    public function destroy(Product $product, ProductGalleryService $gallery): RedirectResponse
    {
        if ($product->variants()->whereHas('orderItems')->exists()) {
            return back()->withErrors(['product' => 'Products with ordered variants cannot be deleted. Archive the piece instead.']);
        }
        $gallery->deleteAll($product);
        $product->delete();

        return to_route('admin.products.index')->with('success', 'Piece removed from the collection.');
    }

    private function validated(ProductRequest $request): array
    {
        $data = $request->validated();

        unset($data['image'], $data['images'], $data['image_alt_texts'], $data['variants']);
        $data['featured'] = $request->boolean('featured');

        unset($data['primary_category_id'], $data['category_ids'], $data['size_ids'], $data['colour_ids'], $data['tag_ids']);

        return $data;
    }

    /** @return array<string, \Illuminate\Support\Collection> */
    private function catalogueOptions(): array
    {
        return [
            'categories' => Category::query()->ordered()->get(['id', 'name', 'parent_id', 'is_active']),
            'sizes' => ProductSize::query()->ordered()->get(['id', 'name', 'code', 'is_active']),
            'colours' => ProductColour::query()->ordered()->get(['id', 'name', 'hex_code', 'is_active']),
            'tags' => ProductTag::query()->ordered()->get(['id', 'name', 'is_active']),
        ];
    }

    private function syncCatalogueAssignments(Product $product, ProductRequest $request): void
    {
        $primary = $request->integer('primary_category_id') ?: null;
        $categoryIds = collect($request->input('category_ids', []))
            ->push($primary)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $product->categories()->sync($categoryIds->mapWithKeys(fn (int $id): array => [$id => ['is_primary' => $id === $primary]])->all());
        $product->sizes()->sync(collect($request->input('size_ids', []))->filter()->map(fn ($id) => (int) $id)->unique()->all());
        $product->colours()->sync(collect($request->input('colour_ids', []))->filter()->map(fn ($id) => (int) $id)->unique()->all());
        $product->tags()->sync(collect($request->input('tag_ids', []))->filter()->map(fn ($id) => (int) $id)->unique()->all());
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function uploads(ProductRequest $request): array
    {
        return array_values(array_filter([
            ...$request->file('images', []),
            $request->file('image'),
        ]));
    }

    public function setPrimaryImage(Product $product, ProductImage $image, ProductGalleryService $gallery): RedirectResponse
    {
        $gallery->setPrimary($product, $image);

        return back()->with('success', 'Primary product image updated.');
    }

    public function destroyImage(Product $product, ProductImage $image, ProductGalleryService $gallery): RedirectResponse
    {
        $gallery->delete($product, $image);

        return back()->with('success', 'Product image removed.');
    }

    public function sortImages(Request $request, Product $product, ProductGalleryService $gallery): RedirectResponse
    {
        $data = $request->validate(['images' => ['required', 'array'], 'images.*' => ['integer', 'min:0']]);
        $gallery->sort($product, $data['images']);

        return back()->with('success', 'Product image order updated.');
    }

    public function generateVariants(Request $request, Product $product, ProductVariantService $variants): RedirectResponse
    {
        $data = $request->validate([
            'size_ids' => ['nullable', 'array'], 'size_ids.*' => ['integer', 'exists:product_sizes,id'],
            'colour_ids' => ['nullable', 'array'], 'colour_ids.*' => ['integer', 'exists:product_colours,id'],
        ]);
        $created = DB::transaction(fn (): int => $variants->generate($product, $data['size_ids'] ?? [], $data['colour_ids'] ?? []));

        return to_route('admin.products.edit', $product)->with('success', $created ? $created.' variant combinations generated. Add stock and save each option.' : 'All selected variant combinations already exist.');
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant, ProductVariantService $variants, ProductStockNotificationService $stockNotifications): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);
        $previousStock = (int) $product->stock;
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'stock' => ['required', 'integer', 'min:0'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $variant->update([
            ...$data,
            'sku' => filled($data['sku'] ?? null) ? strtoupper(trim((string) $data['sku'])) : null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $variants->syncProductStock($product);
        $stockNotifications->handleStockChange($product->fresh(), $previousStock);

        return back()->with('success', 'Product variant updated.');
    }

    public function destroyVariant(Product $product, ProductVariant $variant, ProductVariantService $variants): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);
        if ($variant->orderItems()->exists()) {
            $variant->update(['is_active' => false]);
            $variants->syncProductStock($product);

            return back()->with('success', 'Ordered variants are retained and have been deactivated.');
        }
        $variant->delete();
        $variants->syncProductStock($product);

        return back()->with('success', 'Product variant removed.');
    }
}
