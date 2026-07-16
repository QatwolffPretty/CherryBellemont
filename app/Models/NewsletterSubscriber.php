<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'name', 'status', 'subscribed_at', 'unsubscribed_at', 'source', 'verification_token',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->where('status', 'subscribed');
    }

    public function setEmailAttribute(?string $email): void
    {
        $this->attributes['email'] = mb_strtolower(trim((string) $email));
    }
}
