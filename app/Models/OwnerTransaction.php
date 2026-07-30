<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerTransaction extends Model
{
    protected $fillable = ['transaction_number', 'transaction_date', 'transaction_type', 'amount', 'payment_account_id', 'destination_account_id', 'description', 'payment_method', 'reference_number', 'notes', 'status', 'journal_entry_id', 'created_by', 'updated_by'];
    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2'];

    public function paymentAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'payment_account_id'); }
    public function destinationAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'destination_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
}
