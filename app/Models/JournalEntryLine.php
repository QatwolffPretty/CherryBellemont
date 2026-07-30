<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'description', 'debit', 'credit', 'customer_id', 'supplier_id', 'order_id', 'expense_id', 'owner_transaction_id'];
    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function account(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'account_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function ownerTransaction(): BelongsTo { return $this->belongsTo(OwnerTransaction::class); }
}
