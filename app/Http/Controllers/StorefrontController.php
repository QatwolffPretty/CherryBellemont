<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ReviewEligibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View { return view('storefront.home', ['featuredProducts' => Product::query()->where('status', 'active')->where('featured', true)->withCount('approvedReviews')->withAvg('approvedReviews as approved_reviews_avg_rating', 'rating')->take(3)->get()]); }
    public function collection(Request $request): View
    {
        $products = Product::query()->where('status', 'active')->withCount('approvedReviews')->withAvg('approvedReviews as approved_reviews_avg_rating', 'rating');

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
    public function show(Request $request, Product $product, ReviewEligibility $eligibility): View
    {
        abort_unless($product->status === 'active', 404);

        $reviews = $product->approvedReviews()->with('images');
        if ($search = $request->string('review_search')->trim()->value()) {
            $reviews->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('review', 'like', "%{$search}%"));
        }
        if (in_array((int) $request->input('rating'), [1, 2, 3, 4, 5], true)) {
            $reviews->where('rating', (int) $request->input('rating'));
        }
        if ($request->boolean('verified')) {
            $reviews->where('is_verified_purchase', true);
        }
        if ($request->boolean('with_images')) {
            $reviews->has('images');
        }
        match ($request->input('review_sort')) {
            'oldest' => $reviews->oldest(),
            'highest' => $reviews->orderByDesc('rating')->latest(),
            'lowest' => $reviews->orderBy('rating')->latest(),
            'helpful' => $reviews->orderByDesc('helpful_count')->latest(),
            default => $reviews->latest(),
        };

        $reviewStats = $product->approvedReviews()
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as average_rating')
            ->first();
        $ratingBreakdown = $product->approvedReviews()
            ->selectRaw('rating, COUNT(*) as review_count')
            ->groupBy('rating')
            ->pluck('review_count', 'rating');
        $reviewContext = null;
        if ($request->filled('order_number')) {
            $reviewContext = $eligibility->resolve($request, $product);
        }

        return view('storefront.show', [
            'product' => $product,
            'reviews' => $reviews->paginate(10)->withQueryString(),
            'reviewStats' => $reviewStats,
            'ratingBreakdown' => $ratingBreakdown,
            'reviewContext' => $reviewContext,
        ]);
    }
}
