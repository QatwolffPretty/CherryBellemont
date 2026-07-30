<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductCatalogueService;
use App\Services\ReviewEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View { return view('storefront.home', ['featuredProducts' => Product::query()->where('status', 'active')->where('featured', true)->with(['primaryImage'])->withCount(['approvedReviews', 'variants'])->withAvg('approvedReviews as approved_reviews_avg_rating', 'rating')->take(3)->get()]); }
    public function collection(Request $request, ProductCatalogueService $catalogue): View
    {
        return $this->catalogueView($request, $catalogue);
    }

    /**
     * Keeps legacy /collection/{product-slug} links working while reserving active category slugs
     * for their public landing pages. New product links use /collection/products/{slug}.
     */
    public function categoryOrLegacyProduct(Request $request, string $slug, ProductCatalogueService $catalogue, ReviewEligibility $eligibility): View
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category) {
            return $this->catalogueView($request, $catalogue, $category);
        }

        $product = Product::query()->where('slug', $slug)->firstOrFail();

        return $this->show($request, $product, $eligibility);
    }

    private function catalogueView(Request $request, ProductCatalogueService $catalogue, ?Category $category = null): View
    {
        $filters = $catalogue->filters($request, $category);

        return view('storefront.collection', [
            'products' => $catalogue->products($filters),
            'filters' => $filters,
            'filterOptions' => $catalogue->options(),
            'sortOptions' => ProductCatalogueService::SORTS,
            'category' => $category,
            'categoryStructuredData' => $category ? [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Collection', 'item' => route('collection')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => route('collection.category', $category->slug)],
                ],
            ] : null,
        ]);
    }
    public function show(Request $request, Product $product, ReviewEligibility $eligibility): View
    {
        abort_unless($product->status === 'active', 404);
        $product->load([
            'categories' => fn ($query) => $query->active()->ordered(),
            'sizes' => fn ($query) => $query->active()->ordered(),
            'colours' => fn ($query) => $query->active()->ordered(),
            'tags' => fn ($query) => $query->active()->ordered(),
            'productImages',
            'primaryImage',
            'variants',
            'activeVariants.size',
            'activeVariants.colour',
        ]);

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
            'hasVariants' => $product->hasVariants(),
            'availableStock' => $product->availableStock(),
            'reviews' => $reviews->paginate(10)->withQueryString(),
            'reviewStats' => $reviewStats,
            'ratingBreakdown' => $ratingBreakdown,
            'reviewContext' => $reviewContext,
            'productStructuredData' => $this->productStructuredData($product, $reviewStats),
        ]);
    }

    private function productStructuredData(Product $product, object $reviewStats): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => Str::limit(trim(strip_tags((string) $product->description)), 300, ''),
            'url' => route('products.show', $product),
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'MYR',
                'price' => number_format((float) $product->price, 2, '.', ''),
                'availability' => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($product->primaryImagePath()) {
            $data['image'] = asset('storage/'.$product->primaryImagePath());
        }

        if ((int) $reviewStats->review_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $reviewStats->average_rating, 1, '.', ''),
                'reviewCount' => (int) $reviewStats->review_count,
            ];
        }

        $primaryCategory = $product->categories->first();
        if ($primaryCategory) {
            $data['breadcrumb'] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Collection', 'item' => route('collection')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $primaryCategory->name, 'item' => route('collection.category', ['slug' => $primaryCategory->slug])],
                    ['@type' => 'ListItem', 'position' => 4, 'name' => $product->name, 'item' => route('products.show', $product)],
                ],
            ];
        }

        return $data;
    }
}
