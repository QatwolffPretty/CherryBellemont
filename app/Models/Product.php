<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'price', 'cost_price', 'status', 'featured', 'stock', 'image_path'];
    protected $casts = ['price' => 'decimal:2', 'cost_price' => 'decimal:2', 'featured' => 'boolean', 'stock' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (self $product): void { $product->slug ??= Str::slug($product->name); });
    }

    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function approvedReviews(): HasMany { return $this->reviews()->approved(); }
    public function stockNotifications(): HasMany { return $this->hasMany(ProductStockNotification::class); }
    public function returnRequestItems(): HasMany { return $this->hasMany(ReturnRequestItem::class); }
    public function productImages(): HasMany { return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id'); }
    public function primaryImage(): HasOne { return $this->hasOne(ProductImage::class)->where('is_primary', true); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function activeVariants(): HasMany { return $this->variants()->active(); }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('is_primary')->withTimestamps();
    }

    /** The primary category remains a pivot relationship so one product can still be in multiple collections. */
    public function primaryCategory(): BelongsToMany
    {
        return $this->categories()->wherePivot('is_primary', true)->orderBy('category_product.created_at');
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(ProductSize::class)->withTimestamps();
    }

    public function colours(): BelongsToMany
    {
        return $this->belongsToMany(ProductColour::class)->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->inStock();
    }

    public function primaryImagePath(): ?string
    {
        if ($this->relationLoaded('primaryImage') && $this->primaryImage) {
            return $this->primaryImage->image_path;
        }

        if ($this->relationLoaded('productImages') && $this->productImages->isNotEmpty()) {
            return $this->productImages->firstWhere('is_primary', true)?->image_path
                ?? $this->productImages->first()?->image_path;
        }

        return $this->image_path;
    }

    public function primaryImageUrl(): ?string
    {
        return $this->primaryImagePath() ? Storage::disk('public')->url($this->primaryImagePath()) : null;
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    public function availableStock(): int
    {
        if (! $this->hasVariants()) {
            return (int) $this->stock;
        }

        return $this->relationLoaded('activeVariants')
            ? (int) $this->activeVariants->sum('stock')
            : (int) $this->activeVariants()->sum('stock');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $search) use ($like): void {
            $search->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('categories', fn (Builder $categories) => $categories->where('name', 'like', $like))
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $like))
                ->orWhereHas('colours', fn (Builder $colours) => $colours->where('name', 'like', $like));
        });
    }

    /** @param array<int, string> $slugs */
    public function scopeCategory(Builder $query, array $slugs): Builder
    {
        return $slugs === [] ? $query : $query->whereHas('categories', fn (Builder $categories) => $categories->active()->whereIn('slug', $slugs));
    }

    /** @param array<int, string> $codes */
    public function scopeSize(Builder $query, array $codes): Builder
    {
        return $codes === [] ? $query : $query->whereHas('sizes', fn (Builder $sizes) => $sizes->active()->whereIn('code', $codes));
    }

    /** @param array<int, string> $slugs */
    public function scopeColour(Builder $query, array $slugs): Builder
    {
        return $slugs === [] ? $query : $query->whereHas('colours', fn (Builder $colours) => $colours->active()->whereIn('slug', $slugs));
    }

    /** @param array<int, string> $slugs */
    public function scopeTagged(Builder $query, array $slugs): Builder
    {
        return $slugs === [] ? $query : $query->whereHas('tags', fn (Builder $tags) => $tags->active()->whereIn('slug', $slugs));
    }

    public function scopePriceRange(Builder $query, ?float $minimum, ?float $maximum): Builder
    {
        if ($minimum !== null) {
            $query->where('price', '>=', $minimum);
        }
        if ($maximum !== null) {
            $query->where('price', '<=', $maximum);
        }

        return $query;
    }
}
