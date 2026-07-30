<?php

namespace App\Services;

use App\Models\AccountingSetting;
use App\Support\AccountingCatalog;
use Illuminate\Support\Facades\Cache;

class AccountingSettingsService
{
    private const CACHE_KEY = 'cherry-bellemont.accounting-settings.v1';

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default ?? AccountingCatalog::defaultMappings()[$key] ?? null;
    }

    public function set(string $key, mixed $value, ?int $userId = null): AccountingSetting
    {
        $setting = AccountingSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value, 'type' => $this->typeFor($key), 'updated_by' => $userId]);
        $this->forgetCache();

        return $setting;
    }

    public function accountCode(string $key): string
    {
        return (string) $this->get($key, AccountingCatalog::defaultMappings()[$key] ?? '');
    }

    public function automaticPostingEnabled(): bool
    {
        return filter_var($this->get('automatic_posting_enabled', '1'), FILTER_VALIDATE_BOOL);
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn () => AccountingSetting::query()->pluck('value', 'key')->all());
    }

    public function forgetCache(): void { Cache::forget(self::CACHE_KEY); }

    private function typeFor(string $key): string
    {
        return in_array($key, ['automatic_posting_enabled', 'require_expense_approval'], true) ? 'boolean' : 'string';
    }
}
