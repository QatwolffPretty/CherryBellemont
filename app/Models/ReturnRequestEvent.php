<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestEvent extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['return_request_id', 'actor_type', 'actor_id', 'event_type', 'from_status', 'to_status', 'note', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
}
