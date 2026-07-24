<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestImage extends Model
{
    protected $fillable = ['return_request_id', 'image_path', 'sort_order'];
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
}
