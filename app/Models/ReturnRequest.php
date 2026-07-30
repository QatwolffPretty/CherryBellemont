<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $fillable = ['return_number', 'order_id', 'customer_name', 'customer_email', 'request_type', 'status', 'customer_reason', 'customer_details', 'preferred_resolution', 'admin_decision_reason', 'return_instructions', 'exchange_details', 'requested_at', 'reviewed_at', 'approved_at', 'rejected_at', 'item_received_at', 'inspected_at', 'completed_at', 'reviewed_by'];
    protected $casts = ['exchange_details' => 'array', 'requested_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'item_received_at' => 'datetime', 'inspected_at' => 'datetime', 'completed_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function items(): HasMany { return $this->hasMany(ReturnRequestItem::class); }
    public function images(): HasMany { return $this->hasMany(ReturnRequestImage::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
    public function events(): HasMany { return $this->hasMany(ReturnRequestEvent::class)->orderBy('created_at'); }
    public function notificationLogs(): HasMany { return $this->hasMany(OrderNotificationLog::class); }
    public function scopePendingReview(Builder $query): Builder { return $query->whereIn('status', ['requested', 'under_review']); }
    public function scopeAwaitingReturn(Builder $query): Builder { return $query->where('status', 'awaiting_return'); }
    public function scopeAwaitingInspection(Builder $query): Builder { return $query->whereIn('status', ['item_received', 'inspecting']); }
    public function scopeCompleted(Builder $query): Builder { return $query->whereIn('status', ['completed', 'closed']); }

    public function getRouteKeyName(): string
    {
        return 'return_number';
    }
}
