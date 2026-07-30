<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'subtype', 'description', 'normal_balance', 'parent_id', 'opening_balance', 'opening_balance_date', 'is_active', 'is_system', 'allow_manual_posting', 'is_cash_account', 'is_cash_equivalent', 'is_clearing_account', 'cash_flow_enabled', 'created_by', 'updated_by'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'allow_manual_posting' => 'boolean',
        'is_cash_account' => 'boolean',
        'is_cash_equivalent' => 'boolean',
        'is_clearing_account' => 'boolean',
        'cash_flow_enabled' => 'boolean',
    ];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class, 'account_id'); }
    public function debitExpenses(): HasMany { return $this->hasMany(Expense::class, 'debit_account_id'); }
    public function paymentExpenses(): HasMany { return $this->hasMany(Expense::class, 'payment_account_id'); }
    public function paymentOwnerTransactions(): HasMany { return $this->hasMany(OwnerTransaction::class, 'payment_account_id'); }
    public function destinationOwnerTransactions(): HasMany { return $this->hasMany(OwnerTransaction::class, 'destination_account_id'); }
    public function debitOwnerTransactions(): HasMany { return $this->hasMany(OwnerTransaction::class, 'debit_account_id'); }
    public function creditOwnerTransactions(): HasMany { return $this->hasMany(OwnerTransaction::class, 'credit_account_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function cashFlowMapping(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(CashFlowAccountMapping::class, 'accounting_account_id'); }

    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
    public function scopeSystem(Builder $query): Builder { return $query->where('is_system', true); }
    public function scopeCashFlowEnabled(Builder $query): Builder { return $query->where('cash_flow_enabled', true); }
    public function scopeType(Builder $query, ?string $type): Builder { return blank($type) ? $query : $query->where('type', $type); }
    public function scopeSearchable(Builder $query, ?string $search): Builder
    {
        return blank($search) ? $query : $query->where(fn (Builder $accounts) => $accounts
            ->where('code', 'like', '%'.trim($search).'%')
            ->orWhere('name', 'like', '%'.trim($search).'%'));
    }

    public function isDebitNormal(): bool { return $this->normal_balance === 'debit'; }
    public function displayLabel(): string { return $this->code.' – '.$this->name; }
    public function hasOpeningBalance(): bool { return ! in_array((string) $this->opening_balance, ['0', '0.0', '0.00'], true); }
    public function isParentOf(self $account): bool { return $account->parent_id === $this->id; }
    public function mayBeDeleted(): bool
    {
        return ! $this->is_system
            && ! $this->hasOpeningBalance()
            && ! $this->children()->exists()
            && ! $this->lines()->exists()
            && ! $this->debitExpenses()->exists()
            && ! $this->paymentExpenses()->exists()
            && ! $this->paymentOwnerTransactions()->exists()
            && ! $this->destinationOwnerTransactions()->exists()
            && ! $this->debitOwnerTransactions()->exists()
            && ! $this->creditOwnerTransactions()->exists();
    }
}
