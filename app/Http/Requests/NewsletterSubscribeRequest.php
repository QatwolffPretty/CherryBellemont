<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NewsletterSubscribeRequest extends FormRequest
{
    protected $errorBag = 'newsletter';

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'name' => trim((string) $this->input('name')) ?: null,
            'source' => trim((string) $this->input('source')) ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:80'],
        ];
    }
}
