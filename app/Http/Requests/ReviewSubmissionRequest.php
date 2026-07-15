<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSubmissionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_number' => trim((string) $this->input('order_number')),
            'guest_access_token' => trim((string) $this->input('guest_access_token')) ?: null,
            'customer_email' => mb_strtolower(trim((string) $this->input('customer_email'))),
            'title' => trim((string) $this->input('title')),
            'review' => trim((string) $this->input('review')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:100'],
            'guest_access_token' => ['nullable', 'string', 'size:64'],
            'customer_email' => ['required', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['required', 'string', 'max:160'],
            'review' => ['required', 'string', 'max:4000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer', 'distinct'],
        ];
    }
}
