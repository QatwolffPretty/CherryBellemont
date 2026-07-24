<?php

namespace App\Http\Requests;

use App\Support\SettingsCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSettingMediaRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->is_admin; }

    public function rules(): array
    {
        return [
            'setting_key' => ['required', 'string', Rule::in(collect(SettingsCatalog::definitions())->filter(fn (array $d) => $d['type'] === 'image')->keys()->all())],
            'file' => ['required', 'file', 'max:5120', 'mimes:png,jpg,jpeg,webp,svg'],
        ];
    }
}
