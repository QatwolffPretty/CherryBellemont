<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['expense_number', 'expense_date', 'accounting_date', 'expense_category_id', 'debit_account_id', 'payment_account_id', 'supplier', 'description', 'amount', 'tax_amount', 'payment_method', 'payment_status', 'receipt_path', 'reference_number', 'notes', 'status', 'journal_entry_id', 'approved_by', 'created_by', 'updated_by'];
    protected $casts = ['expense_date' => 'date', 'accounting_date' => 'date', 'amount' => 'decimal:2', 'tax_amount' => 'decimal:2'];

    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function debitAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'debit_account_id'); }
    public function paymentAccount(): BelongsTo { return $this->belongsTo(AccountingAccount::class, 'payment_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
