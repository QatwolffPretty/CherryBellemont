<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCustomerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'filter' => $this->input('filter', 'all'),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['required', 'string', Rule::in([
                'all',
                'registered',
                'guest',
                'newsletter',
                'paid',
                'no_paid_orders',
            ])],
        ];
    }

    /** @return array{search: ?string, filter: string} */
    public function filters(): array
    {
        return $this->validated();
    }
}
