<?php

namespace App\Http\Requests;

use App\Models\AccountingAccount;
use App\Models\OwnerTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerCompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = OwnerTransaction::LEGACY_TYPES[$this->input('transaction_type')] ?? $this->input('transaction_type');

        $this->merge(['transaction_type' => $type]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'transaction_type' => ['required', Rule::in(array_keys(OwnerTransaction::TYPES))],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'description' => ['required', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $type = $this->input('transaction_type');
            $paymentId = $this->integer('payment_account_id') ?: null;
            if (in_array($type, ['salary', 'drawing', 'capital_contribution'], true) && ! $paymentId) {
                $validator->errors()->add('payment_account_id', 'Choose the cash or bank account used for this transaction.');
                return;
            }

            if (! $paymentId) {
                return;
            }

            $account = AccountingAccount::query()->find($paymentId);
            if (! $account || ! $account->is_active) {
                $validator->errors()->add('payment_account_id', 'Choose an active cash or bank account.');
                return;
            }

            if ($account->type !== 'asset' || ! in_array($account->subtype, ['Cash', 'Bank'], true)) {
                $validator->errors()->add('payment_account_id', 'Owner compensation may only use an active Cash or Bank asset account.');
            }
        }];
    }
}
