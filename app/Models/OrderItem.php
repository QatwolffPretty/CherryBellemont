<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'name', 'product_name', 'quantity', 'unit_price', 'total', 'line_total'];
    protected $casts = ['unit_price' => 'decimal:2', 'total' => 'decimal:2', 'line_total' => 'decimal:2'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function review(): HasOne { return $this->hasOne(Review::class); }
    public function returnRequestItems(): HasMany { return $this->hasMany(ReturnRequestItem::class); }
}
