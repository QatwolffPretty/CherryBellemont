<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'minimum_order_amount',
        'maximum_discount_amount', 'usage_limit', 'usage_limit_per_email', 'used_count',
        'starts_at', 'expires_at', 'is_active', 'free_shipping',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'free_shipping' => 'boolean',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Match a human-entered code without relying on database collation. This
     * keeps lookup case-insensitive for older records too.
     */
    public function scopeMatchingCode(Builder $query, string $code): Builder
    {
        return $query->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))]);
    }

    public function setCodeAttribute(?string $code): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $code));
    }
}
