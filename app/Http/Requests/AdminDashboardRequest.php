<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'range' => $this->input('range', 'last_7_days'),
            'from_date' => $this->filled('from_date') ? $this->input('from_date') : null,
            'to_date' => $this->filled('to_date') ? $this->input('to_date') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'range' => ['required', 'string', Rule::in([
                'today',
                'last_7_days',
                'last_30_days',
                'this_month',
                'this_year',
                'custom',
            ])],
            'from_date' => ['nullable', 'date', 'required_if:range,custom'],
            'to_date' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:from_date'],
        ];
    }

    /** @return array{range: string, from_date: ?string, to_date: ?string} */
    public function filters(): array
    {
        return $this->validated();
    }
}
