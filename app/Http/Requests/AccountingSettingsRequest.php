<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['financial_year_start' => ['required', 'date_format:m-d'], 'default_currency' => ['required', 'in:MYR'], 'default_bank_account' => ['required', 'exists:accounting_accounts,code'], 'default_cash_account' => ['required', 'exists:accounting_accounts,code'], 'stripe_clearing_account' => ['required', 'exists:accounting_accounts,code'], 'duitnow_clearing_account' => ['required', 'exists:accounting_accounts,code'], 'product_sales_account' => ['required', 'exists:accounting_accounts,code'], 'shipping_income_account' => ['required', 'exists:accounting_accounts,code'], 'gift_wrapping_income_account' => ['required', 'exists:accounting_accounts,code'], 'sales_discount_account' => ['required', 'exists:accounting_accounts,code'], 'refund_account' => ['required', 'exists:accounting_accounts,code'], 'inventory_asset_account' => ['required', 'exists:accounting_accounts,code'], 'cost_of_goods_sold_account' => ['required', 'exists:accounting_accounts,code'], 'payment_processing_fee_account' => ['required', 'exists:accounting_accounts,code'], 'owner_salary_account' => ['required', 'exists:accounting_accounts,code'], 'owner_drawings_account' => ['required', 'exists:accounting_accounts,code'], 'owner_capital_account' => ['required', 'exists:accounting_accounts,code'], 'business_reserve_account' => ['required', 'exists:accounting_accounts,code'], 'emergency_reserve_account' => ['required', 'exists:accounting_accounts,code'], 'retained_earnings_account' => ['required', 'exists:accounting_accounts,code'], 'automatic_posting_enabled' => ['nullable', 'boolean'], 'require_expense_approval' => ['nullable', 'boolean'], 'journal_entry_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]+$/'], 'opening_balance_date' => ['required', 'date']]; }
}
