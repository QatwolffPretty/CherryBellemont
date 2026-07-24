<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'price', 'status', 'featured', 'stock', 'image_path'];
    protected $casts = ['price' => 'decimal:2', 'featured' => 'boolean', 'stock' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (self $product): void { $product->slug ??= Str::slug($product->name); });
    }

    public function orderItems(): HasMany { return $this->hasMany(OrderItem::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function approvedReviews(): HasMany { return $this->reviews()->approved(); }
    public function stockNotifications(): HasMany { return $this->hasMany(ProductStockNotification::class); }
    public function returnRequestItems(): HasMany { return $this->hasMany(ReturnRequestItem::class); }
}
