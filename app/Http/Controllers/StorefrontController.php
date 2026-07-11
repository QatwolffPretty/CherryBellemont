<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View { return view('storefront.home', ['featuredProducts' => Product::query()->where('status', 'active')->where('featured', true)->take(3)->get()]); }
    public function collection(): View { return view('storefront.collection', ['products' => Product::query()->where('status', 'active')->latest()->paginate(12)]); }
    public function show(Product $product): View { abort_unless($product->status === 'active', 404); return view('storefront.show', compact('product')); }
}
