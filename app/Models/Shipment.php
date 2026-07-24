<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    public const STATUSES = ['draft', 'ready', 'shipped', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_failed', 'returned', 'cancelled'];

    protected $fillable = ['shipment_number', 'order_id', 'courier_id', 'courier_name_snapshot', 'service_name', 'tracking_number', 'tracking_url', 'shipment_status', 'shipment_type', 'label_path', 'admin_note', 'provider_reference', 'api_provider', 'shipped_at', 'estimated_delivery_at', 'delivered_at', 'cancelled_at', 'created_by'];

    protected $casts = ['shipped_at' => 'datetime', 'estimated_delivery_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'shipment_number';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('event_time');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ShipmentAuditLog::class)->latest('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('shipment_status', ['delivered', 'returned', 'cancelled']);
    }
}
