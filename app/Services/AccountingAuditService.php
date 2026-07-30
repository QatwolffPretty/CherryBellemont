<?php

namespace App\Services;

use App\Models\AccountingAuditLog;

class AccountingAuditService
{
    public function record(string $action, object $record, ?int $userId = null, array $old = [], array $new = [], ?string $ip = null): void
    {
        AccountingAuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'record_type' => class_basename($record),
            'record_id' => $record->id ?? null,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_hash' => $ip ? hash('sha256', $ip) : null,
        ]);
    }
}
