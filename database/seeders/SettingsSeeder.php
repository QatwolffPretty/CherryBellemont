<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingsCatalog;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /** Seed only missing values; existing administrator changes remain intact. */
    public function run(): void
    {
        foreach (SettingsCatalog::definitions() as $fullKey => $definition) {
            [$group, $key] = explode('.', $fullKey, 2);

            Setting::query()->firstOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'value' => $this->encode($definition['default'], $definition['type']),
                    'type' => $definition['type'],
                    'is_public' => $definition['public'],
                    'is_encrypted' => false,
                    'description' => $definition['description'],
                ],
            );
        }
    }

    private function encode(mixed $value, string $type): ?string
    {
        if ($value === null) return null;

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'decimal' => number_format((float) $value, 2, '.', ''),
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
}
