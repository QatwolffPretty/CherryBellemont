<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualIncomeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['transaction_date' => ['required', 'date'], 'revenue_account_id' => ['required', 'exists:accounting_accounts,id'], 'deposit_account_id' => ['required', 'different:revenue_account_id', 'exists:accounting_accounts,id'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'reference' => ['nullable', 'string', 'max:100'], 'description' => ['required', 'string', 'max:2000']]; }
}
