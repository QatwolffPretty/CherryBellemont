<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\ExpenseCategory;
use App\Support\AccountingCatalog;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccountingAccountService
{
    public function ensureDefaults(): void
    {
        foreach (AccountingCatalog::accounts() as $definition) {
            AccountingAccount::query()->firstOrCreate(['code' => $definition['code']], $definition + ['is_system' => true, 'is_active' => true, 'opening_balance' => 0]);
        }
        foreach (AccountingCatalog::expenseCategories() as $name => $code) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['default_account_code' => $code, 'is_active' => true]);
        }
        foreach (AccountingCatalog::defaultMappings() as $key => $value) {
            \App\Models\AccountingSetting::query()->firstOrCreate(['key' => $key], ['value' => $value, 'type' => in_array($key, ['automatic_posting_enabled', 'require_expense_approval'], true) ? 'boolean' : 'string']);
        }
    }

    public function mapped(string $settingKey): AccountingAccount
    {
        $this->ensureDefaults();
        $code = app(AccountingSettingsService::class)->accountCode($settingKey);
        return AccountingAccount::query()->active()->where('code', $code)->firstOrFail();
    }

    /** @return array<string, string> */
    public function accountTypes(): array
    {
        return AccountingCatalog::accountTypes();
    }

    /** @return array<int, string> */
    public function subtypesFor(?string $type): array
    {
        return AccountingCatalog::subtypes()[$type] ?? [];
    }

    public function defaultNormalBalance(?string $type, ?string $subtype = null): string
    {
        return AccountingCatalog::defaultNormalBalance((string) $type, $subtype);
    }

    /** @return Collection<int, AccountingAccount> */
    public function eligibleParents(?AccountingAccount $except = null): Collection
    {
        return AccountingAccount::query()
            ->active()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('type')
            ->orderBy('code')
            ->get();
    }

    public function assertCanUseParent(?AccountingAccount $account, ?int $parentId, string $type): void
    {
        if (! $parentId) {
            return;
        }

        if ($account?->id === $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'An account cannot be its own parent.']);
        }

        $parent = AccountingAccount::query()->findOrFail($parentId);

        if (! $parent->is_active) {
            throw ValidationException::withMessages(['parent_id' => 'Choose an active parent account.']);
        }

        if ($parent->type !== $type) {
            throw ValidationException::withMessages(['parent_id' => 'A child account must use the same account type as its parent.']);
        }

        if ($account && $this->wouldCreateCycle($account, $parent)) {
            throw ValidationException::withMessages(['parent_id' => 'This parent would create a circular account hierarchy.']);
        }
    }

    public function assertCanChangeType(AccountingAccount $account, string $newType): void
    {
        if ($account->children()->where('type', '!=', $newType)->exists()) {
            throw ValidationException::withMessages(['type' => 'Change or re-parent child accounts before changing this account type.']);
        }
    }

    public function assertCanDeactivate(AccountingAccount $account): void
    {
        if (! $account->is_system) {
            return;
        }

        if ($account->lines()->exists() || $this->isMapped($account)) {
            throw ValidationException::withMessages(['is_active' => 'This required system account has activity or is used by current financial mappings and cannot be deactivated.']);
        }
    }

    public function deletionBlocker(AccountingAccount $account): ?string
    {
        if ($account->is_system) {
            return 'System accounts cannot be deleted.';
        }

        if ($account->children()->exists()) {
            return 'Accounts with child accounts cannot be deleted.';
        }

        if ($account->lines()->exists()) {
            return 'Accounts with journal activity cannot be deleted. Deactivate the account instead.';
        }

        if ($account->debitExpenses()->exists() || $account->paymentExpenses()->exists()) {
            return 'Accounts referenced by expenses cannot be deleted.';
        }

        if ($account->paymentOwnerTransactions()->exists() || $account->destinationOwnerTransactions()->exists() || $account->debitOwnerTransactions()->exists() || $account->creditOwnerTransactions()->exists()) {
            return 'Accounts referenced by owner compensation records cannot be deleted.';
        }

        if ($this->isMapped($account)) {
            return 'Accounts used by current accounting settings cannot be deleted.';
        }

        if ($account->hasOpeningBalance()) {
            return 'Accounts with an opening balance cannot be deleted. Clear it through an approved adjustment first.';
        }

        return null;
    }

    public function assertCanDelete(AccountingAccount $account): void
    {
        if ($reason = $this->deletionBlocker($account)) {
            throw ValidationException::withMessages(['account' => $reason]);
        }
    }

    private function wouldCreateCycle(AccountingAccount $account, AccountingAccount $candidateParent): bool
    {
        $cursor = $candidateParent;

        while ($cursor) {
            if ($cursor->id === $account->id) {
                return true;
            }

            $cursor = $cursor->parent;
        }

        return false;
    }

    private function isMapped(AccountingAccount $account): bool
    {
        return AccountingSetting::query()->where('value', $account->code)->exists();
    }
}
