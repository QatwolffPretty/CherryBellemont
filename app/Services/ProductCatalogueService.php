<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\ProductSize;
use App\Models\ProductTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductCatalogueService
{
    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'name_asc' => 'Name: A–Z',
        'best_selling' => 'Best Selling',
        'highest_rated' => 'Highest Rated',
    ];

    /** @return array{categories: \Illuminate\Support\Collection, sizes: \Illuminate\Support\Collection, colours: \Illuminate\Support\Collection, tags: \Illuminate\Support\Collection} */
    public function options(): array
    {
        return Cache::remember('catalogue:filter-options', now()->addMinutes(5), fn (): array => [
            'categories' => Category::query()->active()->whereNotNull('parent_id')->ordered()->get(['id', 'name', 'slug', 'parent_id']),
            'sizes' => ProductSize::query()->active()->ordered()->get(['id', 'name', 'code']),
            'colours' => ProductColour::query()->active()->ordered()->get(['id', 'name', 'slug', 'hex_code']),
            'tags' => ProductTag::query()->active()->ordered()->get(['id', 'name', 'slug']),
        ]);
    }

    public function forgetOptionsCache(): void
    {
        Cache::forget('catalogue:filter-options');
    }

    /** @return array{search: string, category: array<int, string>, size: array<int, string>, colour: array<int, string>, tag: array<int, string>, min_price: ?float, max_price: ?float, availability: string, sort: string} */
    public function filters(Request $request, ?Category $category = null): array
    {
        $options = $this->options();
        $allowed = [
            'category' => $options['categories']->pluck('slug')->all(),
            'size' => $options['sizes']->pluck('code')->map(fn (string $code) => strtolower($code))->all(),
            'colour' => $options['colours']->pluck('slug')->all(),
            'tag' => $options['tags']->pluck('slug')->all(),
        ];

        $values = fn (string $key): array => collect((array) $request->input($key, []))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn (string $value) => in_array($value, $allowed[$key], true))
            ->unique()
            ->values()
            ->all();

        $search = preg_replace('/\s+/', ' ', trim((string) $request->input('search', ''))) ?: '';
        $minimum = $this->price($request->input('min_price'));
        $maximum = $this->price($request->input('max_price'));
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            [$minimum, $maximum] = [$maximum, $minimum];
        }
        $availability = (string) $request->input('availability', 'all');

        return [
            'search' => $search,
            'category' => array_values(array_unique(array_merge($category ? [$category->slug] : [], $values('category')))),
            'size' => array_map('strtoupper', $values('size')),
            'colour' => $values('colour'),
            'tag' => $values('tag'),
            'min_price' => $minimum,
            'max_price' => $maximum,
            'availability' => in_array($availability, ['all', 'in_stock', 'out_of_stock'], true) ? $availability : 'all',
            'sort' => array_key_exists((string) $request->input('sort'), self::SORTS) ? (string) $request->input('sort') : 'newest',
        ];
    }

    /** @param array{search: string, category: array<int, string>, size: array<int, string>, colour: array<int, string>, tag: array<int, string>, min_price: ?float, max_price: ?float, availability: string, sort: string} $filters */
    public function products(array $filters): LengthAwarePaginator
    {
        $products = Product::query()
            ->active()
            ->with([
                'primaryImage',
                'primaryCategory' => fn ($query) => $query->active()->select('categories.id', 'categories.name', 'categories.slug'),
                'colours' => fn ($query) => $query->active()->ordered()->select('product_colours.id', 'name', 'slug', 'hex_code'),
                'tags' => fn ($query) => $query->active()->ordered()->select('product_tags.id', 'name', 'slug'),
            ])
            ->withCount(['approvedReviews', 'variants'])
            ->withAvg('approvedReviews as approved_reviews_avg_rating', 'rating')
            ->search($filters['search'])
            ->category($filters['category'])
            ->size($filters['size'])
            ->colour($filters['colour'])
            ->tagged($filters['tag'])
            ->priceRange($filters['min_price'], $filters['max_price']);

        match ($filters['availability']) {
            'in_stock' => $products->inStock(),
            'out_of_stock' => $products->where('stock', '<=', 0),
            default => null,
        };

        $this->sort($products, $filters['sort']);

        return $products->paginate(12)->withQueryString();
    }

    private function sort(Builder $products, string $sort): void
    {
        match ($sort) {
            'featured' => $products->orderByDesc('featured')->latest(),
            'price_asc' => $products->orderBy('price')->orderBy('name'),
            'price_desc' => $products->orderByDesc('price')->orderBy('name'),
            'name_asc' => $products->orderBy('name'),
            'highest_rated' => $products->orderByDesc('approved_reviews_avg_rating')->orderByDesc('approved_reviews_count')->latest(),
            'best_selling' => $products->orderByDesc($this->paidUnitsSoldSubquery())->latest(),
            default => $products->latest(),
        };
    }

    private function paidUnitsSoldSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
            ->whereColumn('order_items.product_id', 'products.id')
            ->where('orders.payment_status', 'paid')
            ->where(fn ($query) => $query->whereNull('orders.order_status')->orWhere('orders.order_status', '!=', 'cancelled'));
    }

    private function price(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return round((float) $value, 2);
    }
}
