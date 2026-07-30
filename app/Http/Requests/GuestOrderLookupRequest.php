<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestOrderLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_number' => strtoupper(trim((string) $this->input('order_number'))),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'email' => ['required', 'email:rfc', 'max:254'],
        ];
    }
}
