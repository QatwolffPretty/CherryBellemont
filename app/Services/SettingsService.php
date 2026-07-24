<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Support\SettingsCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    private const CACHE_KEY = 'cherry-bellemont.settings.v1';

    public function get(string $key, mixed $default = null): mixed
    {
        $definition = SettingsCatalog::definition($key);
        $fallback = $default ?? ($definition['default'] ?? null);
        $stored = $this->all()[$key] ?? null;

        return $stored === null ? $fallback : $this->cast($stored['value'], $stored['type']);
    }

    public function set(string $key, mixed $value, ?int $updatedBy = null, ?string $ip = null): Setting
    {
        $definition = SettingsCatalog::definition($key);
        if (! $definition || $this->isSensitiveKey($key)) {
            throw new \InvalidArgumentException('This setting cannot be managed in the admin settings module.');
        }
        [$group, $name] = explode('.', $key, 2);
        $encoded = $this->encode($value, $definition['type']);
        $setting = DB::transaction(function () use ($group, $name, $definition, $encoded, $updatedBy, $ip): Setting {
            $setting = Setting::query()->where('group', $group)->where('key', $name)->lockForUpdate()->first();
            $oldValue = $setting?->value;
            if (! $setting) {
                $setting = new Setting(['group' => $group, 'key' => $name]);
            }
            $setting->fill(['value' => $encoded, 'type' => $definition['type'], 'is_public' => $definition['public'], 'is_encrypted' => false, 'description' => $definition['description'], 'updated_by' => $updatedBy]);
            $setting->save();
            if ($oldValue !== $encoded && Schema::hasTable('settings_audit_logs')) {
                try {
                    SettingAuditLog::create(['setting_id' => $setting->id, 'group' => $group, 'key' => $name, 'old_value' => $oldValue, 'new_value' => $encoded, 'changed_by' => $updatedBy, 'ip_hash' => $ip ? hash('sha256', $ip) : null]);
                } catch (QueryException $exception) {
                    Log::warning('Settings change was saved without an audit entry because the audit table is unavailable.', ['setting' => $group.'.'.$name, 'exception_class' => $exception::class]);
                }
            }

            return $setting;
        }, 3);
        $this->forgetCache();

        return $setting;
    }

    public function getGroup(string $group): array
    {
        return collect(SettingsCatalog::definitions())->filter(fn ($definition, $key) => str_starts_with($key, $group.'.'))->mapWithKeys(fn ($definition, $key) => [$key => $this->get($key)])->all();
    }

    public function publicSettings(): array
    {
        return collect(SettingsCatalog::definitions())->filter(fn (array $definition) => $definition['public'])->mapWithKeys(fn ($definition, $key) => [$key => $this->get($key)])->all();
    }

    public function forgetCache(): void { Cache::forget(self::CACHE_KEY); }
    public function imageUrl(?string $path, ?string $fallback = null): ?string
    {
        if (blank($path)) return $fallback;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) return $path;
        if (str_starts_with($path, 'images/')) return asset($path);

        return asset('storage/'.$path);
    }

    /** @return array<string, array{value:?string,type:string}> */
    private function all(): array
    {
        try {
            if (! Schema::hasTable('settings')) return [];
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn () => Setting::query()->get(['group', 'key', 'value', 'type'])->mapWithKeys(fn (Setting $setting) => [$setting->group.'.'.$setting->key => ['value' => $setting->value, 'type' => $setting->type]])->all());
        } catch (\Throwable $exception) {
            Log::warning('Database settings are unavailable; config fallbacks are in use.', ['exception_class' => $exception::class]);
            return [];
        }
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) { 'boolean' => filter_var($value, FILTER_VALIDATE_BOOL), 'integer' => (int) $value, 'decimal' => number_format((float) $value, 2, '.', ''), 'json' => json_decode($value ?: 'null', true), default => $value };
    }
    private function encode(mixed $value, string $type): ?string
    {
        if ($value === null) return null;
        return match ($type) { 'boolean' => $value ? '1' : '0', 'integer' => (string) max(0, (int) $value), 'decimal' => number_format(max(0, (float) $value), 2, '.', ''), 'json' => json_encode($value, JSON_THROW_ON_ERROR), default => trim((string) $value) };
    }
    private function isSensitiveKey(string $key): bool { return str_contains($key, 'secret') || str_contains($key, 'password') || str_contains($key, 'app_key') || str_contains($key, 'database'); }
}
