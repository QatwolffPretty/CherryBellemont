<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    protected $fillable = ['return_request_id', 'order_item_id', 'product_id', 'product_name', 'requested_quantity', 'approved_quantity', 'unit_price', 'line_paid_amount', 'reason', 'condition_received', 'inspection_notes', 'stock_disposition', 'restocked_at'];
    protected $casts = ['unit_price' => 'decimal:2', 'line_paid_amount' => 'decimal:2', 'restocked_at' => 'datetime'];
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
