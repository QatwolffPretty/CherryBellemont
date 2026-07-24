<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingAuditLog extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['setting_id', 'group', 'key', 'old_value', 'new_value', 'changed_by', 'ip_hash', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
