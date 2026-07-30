<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_variant_id', 'name', 'product_name', 'sku', 'size_name', 'colour_name', 'variant_description', 'quantity', 'unit_price', 'unit_cost', 'total', 'line_total'];
    protected $casts = ['unit_price' => 'decimal:2', 'unit_cost' => 'decimal:2', 'total' => 'decimal:2', 'line_total' => 'decimal:2'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productVariant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function review(): HasOne { return $this->hasOne(Review::class); }
    public function returnRequestItems(): HasMany { return $this->hasMany(ReturnRequestItem::class); }
}
