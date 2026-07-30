<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description', 'updated_by'];
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
