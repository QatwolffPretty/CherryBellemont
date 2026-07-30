<?php

namespace App\Http\Requests;

use App\Models\OwnerTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerCompensationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:today,this_month,last_month,this_quarter,this_year,custom'],
            'from_date' => ['nullable', 'date', 'required_if:range,custom'],
            'to_date' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:from_date'],
            'transaction_type' => ['nullable', Rule::in(array_keys(OwnerTransaction::TYPES))],
            'status' => ['nullable', 'in:draft,posted,reversed,cancelled'],
            'payment_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:100'],
            'transaction_number' => ['nullable', 'string', 'max:40'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'posted_by' => ['nullable', 'integer', 'exists:users,id'],
            'minimum_amount' => ['nullable', 'decimal:0,2', 'gte:0'],
            'maximum_amount' => ['nullable', 'decimal:0,2', 'gte:minimum_amount'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_merge(['range' => 'this_year'], $this->validated());
    }
}
