<?php

namespace App\Http\Requests;

use App\Models\AccountingAccount;
use App\Services\AccountingAccountService;
use App\Support\AccountingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountingAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $type = strtolower((string) $this->input('type'));
        $subtype = filled($this->input('subtype')) ? trim((string) $this->input('subtype')) : null;

        $this->merge([
            'code' => preg_replace('/\s+/', '', (string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => strtolower((string) ($this->input('normal_balance') ?: AccountingCatalog::defaultNormalBalance($type, $subtype))),
        ]);
    }

    public function rules(): array
    {
        $account = $this->account();
        $type = (string) $this->input('type');
        $subtypes = AccountingCatalog::subtypes()[$type] ?? [];

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^\d{3,20}$/', Rule::unique('accounting_accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(AccountingCatalog::accountTypes()))],
            'subtype' => ['nullable', 'string', 'max:60', Rule::in($subtypes)],
            'description' => ['nullable', 'string', 'max:2000'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'parent_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'opening_balance' => ['nullable', 'decimal:0,2'],
            'opening_balance_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'allow_manual_posting' => ['nullable', 'boolean'],
            'is_cash_account' => ['nullable', 'boolean'],
            'is_cash_equivalent' => ['nullable', 'boolean'],
            'is_clearing_account' => ['nullable', 'boolean'],
            'cash_flow_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $account = $this->account();
            $service = app(AccountingAccountService::class);
            $type = (string) $this->input('type');
            $subtype = $this->input('subtype');

            if (! AccountingCatalog::allowsNormalBalance($type, $subtype, (string) $this->input('normal_balance'))) {
                $validator->errors()->add('normal_balance', 'The selected normal balance is not appropriate for this account type and subtype.');
            }

            if (! in_array((string) $this->input('opening_balance'), ['', '0', '0.0', '0.00'], true) && ! $this->filled('opening_balance_date')) {
                $validator->errors()->add('opening_balance_date', 'An opening balance date is required when an opening balance is entered.');
            }

            $cashEnabled = $this->boolean('cash_flow_enabled');
            $cashLike = $this->boolean('is_cash_account') || $this->boolean('is_cash_equivalent');
            if ($cashEnabled && ($type !== 'asset' || ! $cashLike || $this->input('normal_balance') !== 'debit')) {
                $validator->errors()->add('cash_flow_enabled', 'Cash Flow accounts must be debit-normal asset accounts marked as cash or cash equivalent.');
            }
            if ($this->boolean('is_clearing_account') && ! $this->boolean('is_cash_equivalent')) {
                $validator->errors()->add('is_clearing_account', 'A clearing account must also be marked as a cash equivalent.');
            }

            try {
                $service->assertCanUseParent($account, $this->integer('parent_id') ?: null, $type);

                if ($account && $account->type !== $type) {
                    $service->assertCanChangeType($account, $type);
                }
            } catch (\Illuminate\Validation\ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }

            if ($account?->is_system) {
                if ($this->input('code') !== $account->code) {
                    $validator->errors()->add('code', 'System account codes cannot be changed.');
                }
                if ($type !== $account->type) {
                    $validator->errors()->add('type', 'System account types cannot be changed.');
                }
                if ($this->input('normal_balance') !== $account->normal_balance) {
                    $validator->errors()->add('normal_balance', 'System account normal balances cannot be changed.');
                }
                if ($this->input('subtype') !== $account->subtype) {
                    $validator->errors()->add('subtype', 'System account subtypes cannot be changed.');
                }
                if ((int) ($this->input('parent_id') ?: 0) !== (int) ($account->parent_id ?: 0)) {
                    $validator->errors()->add('parent_id', 'System account hierarchy cannot be changed.');
                }
            }
        });
    }

    public function account(): ?AccountingAccount
    {
        $account = $this->route('account');

        return $account instanceof AccountingAccount ? $account : null;
    }
}
