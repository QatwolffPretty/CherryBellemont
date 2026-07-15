<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['user_id', 'number', 'order_number', 'guest_access_token', 'status', 'order_status', 'payment_method', 'payment_status', 'subtotal', 'total', 'shipping_address', 'full_name', 'email', 'phone', 'customer_name', 'customer_email', 'customer_phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'customer_notes', 'shipping_zone_id', 'delivery_method_id', 'shipping_method_name', 'shipping_fee', 'pickup_location', 'delivery_instructions', 'courier_name', 'tracking_number', 'shipped_at', 'delivered_at', 'cancelled_at', 'cancellation_reason', 'admin_notes', 'stock_restored_at'];
    protected $casts = ['subtotal' => 'decimal:2', 'total' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'shipping_address' => 'array', 'shipped_at'=>'datetime', 'delivered_at'=>'datetime', 'cancelled_at'=>'datetime', 'stock_restored_at'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function shippingZone(): BelongsTo { return $this->belongsTo(ShippingZone::class); }
    public function deliveryMethod(): BelongsTo { return $this->belongsTo(DeliveryMethod::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function paymentReceipts(): HasMany { return $this->hasMany(PaymentReceipt::class); }
}
