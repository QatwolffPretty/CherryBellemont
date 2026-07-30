<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotificationLog extends Model
{
    protected $fillable = [
        'order_id', 'return_request_id', 'notification_type', 'recipient', 'subject',
        'event_key', 'status', 'is_manual_resend', 'resent_by', 'attempts', 'queued_at',
        'sent_at', 'failed_at', 'error_message', 'metadata',
    ];

    protected $casts = [
        'is_manual_resend' => 'boolean',
        'metadata' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function resentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resent_by');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query->where('recipient', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('notification_type', 'like', "%{$search}%")
                ->orWhereHas('order', fn (Builder $orders) => $orders
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%"));
        });
    }
}
