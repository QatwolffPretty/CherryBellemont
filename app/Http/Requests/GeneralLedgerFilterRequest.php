<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneralLedgerFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:today,this_week,this_month,last_month,this_quarter,this_year,last_year,custom'],
            'from_date' => ['nullable', 'date', 'required_if:range,custom'],
            'to_date' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:from_date'],
            'account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'account_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-\s]*$/'],
            'account_type' => ['nullable', 'in:asset,liability,equity,revenue,cost_of_goods_sold,expense'],
            'account_subtype' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'kind' => ['nullable', 'in:all,system,custom'],
            'normal_balance' => ['nullable', 'in:debit,credit'],
            'activity' => ['nullable', 'in:all,with,without'],
            'min_closing' => ['nullable', 'decimal:0,2'],
            'max_closing' => ['nullable', 'decimal:0,2', 'gte:min_closing'],
            'search' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', 'string', 'max:60'],
            'movement' => ['nullable', 'in:debit,credit'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_merge([
            'range' => 'this_year',
            'status' => 'all',
            'kind' => 'all',
            'activity' => 'all',
        ], $this->validated());
    }
}
