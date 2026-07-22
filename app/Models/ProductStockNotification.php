<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockNotification extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'product_id', 'email', 'waiting_email', 'name', 'status', 'notification_token', 'requested_at',
        'sending_at', 'notified_at', 'cancelled_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'sending_at' => 'datetime',
        'notified_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeNotified(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NOTIFIED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function setEmailAttribute(?string $email): void
    {
        $this->attributes['email'] = mb_strtolower(trim((string) $email));
    }
}
