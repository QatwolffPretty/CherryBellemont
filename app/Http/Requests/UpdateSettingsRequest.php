<?php

namespace App\Http\Requests;

use App\Support\SettingsCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        $rules = ['group' => ['required', 'string', 'max:60'], 'settings' => ['required', 'array']];

        foreach (array_keys((array) $this->input('settings', [])) as $key) {
            $definition = SettingsCatalog::definition($key);
            $rules['settings.'.str_replace('.', '\\.', $key)] = $definition ? $this->rulesFor($key, $definition['type']) : ['prohibited'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $groups = array_filter(explode(',', (string) $this->input('group')));
            foreach (array_keys((array) $this->input('settings', [])) as $key) {
                $belongsToSection = collect($groups)->contains(fn (string $group): bool => str_starts_with($key, $group.'.'));
                if (! SettingsCatalog::has($key) || ! $belongsToSection) {
                    $validator->errors()->add('settings.'.$key, 'This setting is not available in this section.');
                }
            }
        });
    }

    /** @return array<int, mixed> */
    private function rulesFor(string $key, string $type): array
    {
        $rules = match ($type) {
            'boolean' => ['required', 'boolean'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'integer' => ['required', 'integer', 'min:0', 'max:10000'],
            'decimal' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'json' => ['nullable', 'json'],
            'text' => ['nullable', 'string', 'max:5000'],
            default => ['nullable', 'string', 'max:255'],
        };

        if ($key === 'store.currency') $rules[] = 'in:MYR';
        if (in_array($key, ['returns.window_days', 'returns.damaged_report_days', 'returns.maximum_images', 'returns.maximum_image_size_mb', 'gift.message_max_length'], true)) $rules = ['required', 'integer', 'min:1', 'max:10000'];

        return $rules;
    }
}
