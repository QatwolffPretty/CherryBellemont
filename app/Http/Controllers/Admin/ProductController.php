<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductStockNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()->latest();

        if ($request->boolean('low_stock')) {
            $products->where('stock', '<=', 5);
        }

        return view('admin.products.index', ['products' => $products->paginate(20)->withQueryString()]);
    }
    public function create(): View { return view('admin.products.form', ['product' => new Product]); }
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return to_route('admin.products.index')->with('success', 'Piece added to the collection.');
    }
    public function edit(Product $product): View
    {
        $product->loadCount([
            'stockNotifications as waiting_stock_notifications_count' => fn ($query) => $query->waiting(),
            'stockNotifications as notified_stock_notifications_count' => fn ($query) => $query->notified(),
        ]);

        return view('admin.products.form', compact('product'));
    }
    public function update(Request $request, Product $product, ProductStockNotificationService $stockNotifications): RedirectResponse
    {
        $data = $this->validated($request);
        $previousStock = (int) $product->stock;

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        $stockNotifications->handleStockChange($product, $previousStock);

        return to_route('admin.products.index')->with('success', 'Piece updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return to_route('admin.products.index')->with('success', 'Piece removed from the collection.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,active,archived'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($data['image']);
        $data['featured'] = $request->boolean('featured');

        return $data;
    }
}
