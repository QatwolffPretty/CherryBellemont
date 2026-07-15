<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    protected $fillable = ['stripe_event_id', 'event_type', 'processed_at', 'payload', 'processing_error'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
