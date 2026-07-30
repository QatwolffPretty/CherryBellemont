<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['expense_date' => ['required', 'date'], 'accounting_date' => ['required', 'date'], 'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'], 'debit_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'], 'payment_account_id' => ['required', 'integer', 'different:debit_account_id', 'exists:accounting_accounts,id'], 'supplier' => ['nullable', 'string', 'max:150'], 'description' => ['required', 'string', 'max:2000'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'tax_amount' => ['nullable', 'decimal:0,2', 'gte:0'], 'payment_method' => ['nullable', 'string', 'max:40'], 'reference_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000'], 'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]; }
}
