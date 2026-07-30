<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = ['entry_number', 'transaction_date', 'posting_date', 'source_type', 'source_id', 'source_event', 'reference', 'description', 'status', 'currency', 'total_debit', 'total_credit', 'posted_at', 'posted_by', 'reversed_at', 'reversed_by', 'reversal_entry_id', 'created_by', 'updated_by'];

    protected $casts = ['transaction_date' => 'date', 'posting_date' => 'datetime', 'total_debit' => 'decimal:2', 'total_credit' => 'decimal:2', 'posted_at' => 'datetime', 'reversed_at' => 'datetime'];

    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class); }
    public function poster(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }
    public function reverser(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function reversalEntry(): BelongsTo { return $this->belongsTo(self::class, 'reversal_entry_id'); }
    public function sourceOrder(): BelongsTo { return $this->belongsTo(Order::class, 'source_id'); }

    public function scopePosted(Builder $query): Builder { return $query->whereIn('status', ['posted', 'reversed']); }
    public function scopeDrafts(Builder $query): Builder { return $query->where('status', 'draft'); }

    public function isPosted(): bool { return in_array($this->status, ['posted', 'reversed'], true); }
    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isBalanced(): bool { return (string) $this->total_debit === (string) $this->total_credit; }
    public function sourceLabel(): string { return $this->source_type ? str($this->source_type)->replace('_', ' ')->title()->toString() : 'Manual journal'; }
}
