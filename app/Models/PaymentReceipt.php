<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    protected $fillable = ['order_id', 'path', 'storage_disk', 'original_filename', 'mime_type', 'file_size', 'status', 'submitted_at', 'rejection_reason', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function storageDisk(): string
    {
        return in_array($this->storage_disk, ['local', 'public'], true) ? $this->storage_disk : 'public';
    }
}
