<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $fillable = [
        'product_id', 'order_id', 'order_item_id', 'customer_name', 'customer_email',
        'rating', 'title', 'review', 'is_verified_purchase', 'is_approved', 'status',
        'admin_reply', 'approved_at', 'helpful_count',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'helpful_count' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where('is_approved', true);
    }

    public function customerFirstName(): string
    {
        return (string) Str::of($this->customer_name)->trim()->before(' ');
    }
}
