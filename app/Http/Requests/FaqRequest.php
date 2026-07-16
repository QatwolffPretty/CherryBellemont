<?php

namespace App\Http\Requests;

use App\Services\FaqSanitizer;
use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'question' => trim((string) $this->input('question')),
            'answer' => app(FaqSanitizer::class)->sanitize((string) $this->input('answer')),
            'category' => trim((string) $this->input('category')) ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
