<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = ['refund_number', 'return_request_id', 'order_id', 'payment_provider', 'refund_type', 'status', 'amount', 'shipping_refund_amount', 'gift_wrap_refund_amount', 'currency', 'reason', 'stripe_refund_id', 'stripe_payment_intent_id', 'manual_reference', 'manual_proof_path', 'failure_reason', 'requested_at', 'processed_at', 'confirmed_at', 'processed_by'];
    protected $casts = ['amount' => 'decimal:2', 'shipping_refund_amount' => 'decimal:2', 'gift_wrap_refund_amount' => 'decimal:2', 'requested_at' => 'datetime', 'processed_at' => 'datetime', 'confirmed_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
    public function scopeRefundable(Builder $query): Builder { return $query->whereIn('status', ['pending', 'processing']); }
    public function scopeFailedRefunds(Builder $query): Builder { return $query->where('status', 'failed'); }
}
