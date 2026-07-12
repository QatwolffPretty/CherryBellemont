<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View { return view('storefront.home', ['featuredProducts' => Product::query()->where('status', 'active')->where('featured', true)->take(3)->get()]); }
    public function collection(Request $request): View
    {
        $products = Product::query()->where('status', 'active');

        if ($search = $request->string('search')->trim()->value()) {
            $products->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        match ($request->input('sort')) {
            'price_asc' => $products->orderBy('price'),
            'price_desc' => $products->orderByDesc('price'),
            'featured' => $products->orderByDesc('featured')->latest(),
            default => $products->latest(),
        };

        return view('storefront.collection', ['products' => $products->paginate(12)->withQueryString()]);
    }
    public function show(Product $product): View { abort_unless($product->status === 'active', 404); return view('storefront.show', compact('product')); }
}
