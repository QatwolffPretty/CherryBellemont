<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerTransactionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['transaction_date' => ['required', 'date'], 'transaction_type' => ['required', Rule::in(['owner_salary', 'owner_drawing', 'owner_capital', 'business_reserve_allocation', 'emergency_reserve_allocation', 'retained_earnings_allocation'])], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'payment_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'], 'destination_account_id' => ['required', 'integer', 'different:payment_account_id', 'exists:accounting_accounts,id'], 'description' => ['required', 'string', 'max:2000'], 'payment_method' => ['nullable', 'string', 'max:40'], 'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000']]; }
}
