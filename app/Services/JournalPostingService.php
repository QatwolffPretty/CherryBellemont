<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the manual journal-entry lifecycle. Automated commerce postings remain
 * in AccountingPostingService and may use these same JournalEntry records.
 */
class JournalPostingService
{
    public function __construct(
        private readonly AccountingSettingsService $settings,
        private readonly AccountingAuditService $audit,
    ) {}

    /** @param array<string, mixed> $attributes @param array<int, array<string, mixed>> $lines */
    public function createDraft(array $attributes, array $lines, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($attributes, $lines, $userId): JournalEntry {
            $lines = $this->validatedLines($lines);
            $entry = JournalEntry::query()->create([
                'entry_number' => $this->entryNumber(),
                'transaction_date' => $attributes['transaction_date'],
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'],
                'source_type' => $attributes['source_type'] ?? 'manual_journal',
                'source_id' => $attributes['source_id'] ?? null,
                'source_event' => $attributes['source_event'] ?? null,
                'status' => 'draft',
                'currency' => $attributes['currency'] ?? 'MYR',
                'total_debit' => $this->decimal($this->sum($lines, 'debit')),
                'total_credit' => $this->decimal($this->sum($lines, 'credit')),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $this->persistLines($entry, $lines);
            $this->audit->record('journal.created', $entry, $userId, [], ['status' => 'draft', 'entry_number' => $entry->entry_number]);

            return $entry;
        }, 3);
    }

    /** @param array<string, mixed> $attributes @param array<int, array<string, mixed>> $lines */
    public function updateDraft(JournalEntry $entry, array $attributes, array $lines, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $attributes, $lines, $userId): JournalEntry {
            $entry = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($entry->id);
            $this->assertDraft($entry);
            $lines = $this->validatedLines($lines);
            $old = $entry->only(['transaction_date', 'reference', 'description', 'total_debit', 'total_credit']);
            $entry->update([
                'transaction_date' => $attributes['transaction_date'],
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'],
                'total_debit' => $this->decimal($this->sum($lines, 'debit')),
                'total_credit' => $this->decimal($this->sum($lines, 'credit')),
                'updated_by' => $userId,
            ]);
            $entry->lines()->delete();
            $this->persistLines($entry, $lines);
            $this->audit->record('journal.updated', $entry, $userId, $old, $entry->only(['transaction_date', 'reference', 'description', 'total_debit', 'total_credit']));

            return $entry->fresh('lines.account');
        }, 3);
    }

    public function post(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId): JournalEntry {
            $entry = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status === 'posted') {
                return $entry;
            }

            $this->assertDraft($entry);
            $lines = $this->validatedLines($entry->lines->map(fn (JournalEntryLine $line) => $line->only(['account_id', 'description', 'debit', 'credit', 'customer_id', 'supplier_id', 'order_id', 'expense_id', 'owner_transaction_id']))->all());
            $debits = $this->sum($lines, 'debit');
            $credits = $this->sum($lines, 'credit');
            $entry->update([
                'status' => 'posted',
                'posting_date' => now(),
                'posted_at' => now(),
                'posted_by' => $userId,
                'total_debit' => $this->decimal($debits),
                'total_credit' => $this->decimal($credits),
                'updated_by' => $userId,
            ]);
            $this->audit->record('journal.posted', $entry, $userId, ['status' => 'draft'], ['status' => 'posted']);

            return $entry->fresh('lines.account');
        }, 3);
    }

    public function reverse(JournalEntry $entry, ?int $userId = null, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId, $reason): JournalEntry {
            $entry = JournalEntry::query()->with('lines.account')->lockForUpdate()->findOrFail($entry->id);

            if ($entry->status === 'reversed' && $entry->reversal_entry_id) {
                return $entry->reversalEntry()->firstOrFail();
            }

            if ($entry->status !== 'posted') {
                throw ValidationException::withMessages(['journal' => 'Only posted journal entries can be reversed.']);
            }

            $lines = $entry->lines->map(fn (JournalEntryLine $line) => [
                'account_id' => $line->account_id,
                'description' => $line->description,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
                'order_id' => $line->order_id,
                'expense_id' => $line->expense_id,
                'owner_transaction_id' => $line->owner_transaction_id,
            ])->all();
            $lines = $this->validatedLines($lines, false);
            $reversal = JournalEntry::query()->create([
                'entry_number' => $this->entryNumber(),
                'transaction_date' => now()->toDateString(),
                'posting_date' => now(),
                'reference' => 'REV-'.$entry->entry_number,
                'description' => 'Reversal of '.$entry->entry_number.($reason ? ': '.trim($reason) : ''),
                'source_type' => 'journal_reversal',
                'source_id' => $entry->id,
                'source_event' => 'reversal',
                'status' => 'posted',
                'currency' => $entry->currency,
                'total_debit' => $this->decimal($this->sum($lines, 'debit')),
                'total_credit' => $this->decimal($this->sum($lines, 'credit')),
                'posted_at' => now(),
                'posted_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $this->persistLines($reversal, $lines);
            $entry->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $userId, 'reversal_entry_id' => $reversal->id, 'updated_by' => $userId]);
            $this->audit->record('journal.reversed', $entry, $userId, ['status' => 'posted'], ['status' => 'reversed', 'reversal_entry_id' => $reversal->id]);

            return $reversal;
        }, 3);
    }

    public function cancel(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $userId): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $this->assertDraft($entry);
            $entry->update(['status' => 'cancelled', 'updated_by' => $userId]);
            $this->audit->record('journal.cancelled', $entry, $userId, ['status' => 'draft'], ['status' => 'cancelled']);

            return $entry;
        }, 3);
    }

    /** @param array<int, array<string, mixed>> $lines @return array<int, array<string, mixed>> */
    private function validatedLines(array $lines, bool $requireManualPosting = true): array
    {
        $lines = array_values(array_filter($lines, fn (array $line) => filled($line['account_id'] ?? null) || filled($line['debit'] ?? null) || filled($line['credit'] ?? null) || filled($line['description'] ?? null)));

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal entry requires at least two lines.']);
        }

        $errors = [];
        foreach ($lines as $index => &$line) {
            $account = AccountingAccount::query()->find($line['account_id'] ?? null);
            if (! $account) {
                $errors["lines.$index.account_id"][] = 'Select a valid account.';
                continue;
            }
            if ($requireManualPosting && ! $account->is_active) {
                $errors["lines.$index.account_id"][] = 'Inactive accounts cannot be used in a journal.';
            }
            if ($requireManualPosting && ! $account->allow_manual_posting) {
                $errors["lines.$index.account_id"][] = 'Manual posting is disabled for this account.';
            }

            $debit = $this->money($line['debit'] ?? 0);
            $credit = $this->money($line['credit'] ?? 0);
            if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
                $errors["lines.$index"][] = 'Each line requires either one positive debit or one positive credit amount.';
            }
            $line = [
                'account_id' => $account->id,
                'description' => filled($line['description'] ?? null) ? trim((string) $line['description']) : null,
                'debit' => $this->decimal($debit),
                'credit' => $this->decimal($credit),
                'customer_id' => $line['customer_id'] ?? null,
                'supplier_id' => $line['supplier_id'] ?? null,
                'order_id' => $line['order_id'] ?? null,
                'expense_id' => $line['expense_id'] ?? null,
                'owner_transaction_id' => $line['owner_transaction_id'] ?? null,
            ];
        }
        unset($line);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($this->sum($lines, 'debit') !== $this->sum($lines, 'credit')) {
            throw ValidationException::withMessages(['lines' => 'Total debits must equal total credits before a journal can be saved or posted.']);
        }

        return $lines;
    }

    private function assertDraft(JournalEntry $entry): void
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages(['journal' => 'Only draft journal entries can be edited, posted, or cancelled.']);
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function persistLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $line) {
            $entry->lines()->create($line);
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function sum(array $lines, string $key): int
    {
        return array_sum(array_map(fn (array $line) => $this->money($line[$key] ?? 0), $lines));
    }

    private function money(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if ($value === '') return 0;
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw ValidationException::withMessages(['lines' => 'Journal amounts must be non-negative monetary values with at most two decimal places.']);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function decimal(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    private function entryNumber(): string
    {
        $sequence = (int) (JournalEntry::query()->lockForUpdate()->max('id') ?? 0) + 1;

        return (string) $this->settings->get('journal_entry_prefix', 'JE').'-'.now()->format('Y').'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
