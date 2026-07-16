<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    protected $fillable = ['customer_email', 'admin_id', 'note'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function setCustomerEmailAttribute(?string $email): void
    {
        $this->attributes['customer_email'] = mb_strtolower(trim((string) $email));
    }
}
