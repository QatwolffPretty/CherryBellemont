<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashFlowFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_year', 'custom'])],
            'from_date' => ['nullable', 'date', 'required_if:range,custom'],
            'to_date' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:from_date'],
            'cash_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'classification' => ['nullable', Rule::in(['operating', 'investing', 'financing', 'internal_transfer', 'non_cash', 'unclassified'])],
            'category' => ['nullable', 'string', 'max:60'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'source_type' => ['nullable', 'string', 'max:60'],
            'journal_number' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:100'],
            'minimum_amount' => ['nullable', 'decimal:0,2'],
            'maximum_amount' => ['nullable', 'decimal:0,2', 'gte:minimum_amount'],
            'include_clearing' => ['nullable', 'boolean'],
            'include_internal_transfers' => ['nullable', 'boolean'],
            'include_non_cash_activity' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_merge([
            'range' => 'this_year',
            'include_clearing' => true,
            'include_internal_transfers' => false,
            'include_non_cash_activity' => false,
        ], $this->validated());
    }
}
