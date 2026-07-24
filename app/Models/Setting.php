<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_public', 'is_encrypted', 'description', 'updated_by'];
    protected $casts = ['is_public' => 'boolean', 'is_encrypted' => 'boolean'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SettingAuditLog::class);
    }
}
