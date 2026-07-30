<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashFlowMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array'],
            'mappings.*.accounting_account_id' => ['required', 'integer', 'distinct', 'exists:accounting_accounts,id'],
            'mappings.*.classification' => ['required', Rule::in(['operating', 'investing', 'financing', 'internal_transfer', 'non_cash', 'unclassified'])],
            'mappings.*.category_key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'mappings.*.label' => ['nullable', 'string', 'max:120'],
            'mappings.*.display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'mappings.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
