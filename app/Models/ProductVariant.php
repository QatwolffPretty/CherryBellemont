<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'product_size_id', 'product_colour_id', 'sku',
        'price_override', 'stock', 'is_active',
    ];

    protected $casts = [
        'price_override' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(ProductColour::class, 'product_colour_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function displayDescription(): ?string
    {
        return collect([$this->relationLoaded('colour') ? $this->colour?->name : null, $this->relationLoaded('size') ? $this->size?->name : null])
            ->filter()
            ->implode(' / ') ?: null;
    }
}
