<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'tracking_url_template' => blank($this->input('tracking_url_template')) ? null : trim((string) $this->input('tracking_url_template')),
            'website_url' => blank($this->input('website_url')) ? null : trim((string) $this->input('website_url')),
        ]);
    }

    public function rules(): array
    {
        $courier = $this->route('courier');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('couriers', 'code')->ignore($courier)],
            'tracking_url_template' => ['nullable', 'string', 'max:2048', 'regex:/^https?:\/\/[^\s]+$/i'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $template = (string) $this->input('tracking_url_template');
            if ($template !== '' && ! str_contains($template, '{tracking_number}')) {
                $validator->errors()->add('tracking_url_template', 'Include the {tracking_number} placeholder in the tracking URL template.');
            }
        });
    }
}
