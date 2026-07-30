<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class OwnerTransaction extends Model
{
    public const TYPES = [
        'salary' => 'Owner Salary',
        'drawing' => 'Owner Drawing',
        'capital_contribution' => 'Owner Capital Contribution',
        'business_reserve' => 'Business Reserve Allocation',
        'emergency_reserve' => 'Emergency Reserve Allocation',
    ];

    /** Legacy values remain readable so historic owner records are not rewritten. */
    public const LEGACY_TYPES = [
        'owner_salary' => 'salary',
        'owner_drawing' => 'drawing',
        'owner_capital' => 'capital_contribution',
        'business_reserve_allocation' => 'business_reserve',
        'emergency_reserve_allocation' => 'emergency_reserve',
    ];

    protected $fillable = ['transaction_number', 'transaction_date', 'transaction_type', 'amount', 'payment_account_id', 'destination_account_id', 'debit_account_id', 'credit_account_id', 'description', 'payment_method', 'reference_number', 'attachment_path', 'notes', 'status', 'journal_entry_id', 'posted_by', 'posted_at', 'reversed_at', 'reversal_transaction_id', 'created_by', 'updated_by'];
    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];

    public function paymentAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'payment_account_id'); }
    public function destinationAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'destination_account_id'); }
    public function debitAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'debit_account_id'); }
    public function creditAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'credit_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function poster(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }
    public function reversalTransaction(): BelongsTo { return $this->belongsTo(self::class, 'reversal_transaction_id'); }
    public function reversedTransaction(): HasOne { return $this->hasOne(self::class, 'reversal_transaction_id'); }

    public function canonicalType(): string { return self::LEGACY_TYPES[$this->transaction_type] ?? $this->transaction_type; }
    public function typeLabel(): string { return self::TYPES[$this->canonicalType()] ?? str($this->transaction_type)->replace('_', ' ')->title()->toString(); }
    public function mayBePosted(): bool { return $this->status === 'draft' && ! $this->journal_entry_id; }
    public function isPosted(): bool { return $this->status === 'posted'; }
    public function isImmutable(): bool { return in_array($this->status, ['posted', 'reversed'], true); }
    public function attachmentUrl(): ?string { return $this->attachment_path && Storage::disk('local')->exists($this->attachment_path) ? route('admin.accounting.owner-transactions.attachment', $this) : null; }
}
