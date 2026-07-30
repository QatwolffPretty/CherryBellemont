<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingDateRangeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['range' => ['nullable', 'in:today,yesterday,this_week,last_week,this_month,last_month,this_quarter,this_year,custom'], 'from_date' => ['nullable', 'date', 'required_if:range,custom'], 'to_date' => ['nullable', 'date', 'required_if:range,custom', 'after_or_equal:from_date'], 'account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'], 'account_type' => ['nullable', 'in:asset,liability,equity,revenue,cost_of_goods_sold,expense'], 'reference' => ['nullable', 'string', 'max:100'], 'source_type' => ['nullable', 'string', 'max:60'], 'movement' => ['nullable', 'in:debit,credit'], 'order_id' => ['nullable', 'integer', 'exists:orders,id']]; }
    public function filters(): array { return $this->validated() + ['range' => $this->input('range', 'this_month')]; }
}
