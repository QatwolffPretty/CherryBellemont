<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostics for posted accounting activity. It deliberately never
 * changes historical journals; accounting corrections remain reversal-based.
 */
class LedgerIntegrityService
{
    /** @var array<int, string> */
    private const POSTED_STATUSES = ['posted', 'reversed'];

    /** @return array{unbalanced_journals:int,total_debits:int,total_credits:int,difference:int} */
    public function summary(): array
    {
        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', self::POSTED_STATUSES)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as debits, COALESCE(SUM(journal_entry_lines.credit), 0) as credits')
            ->first();
        $debits = $this->money($totals?->getAttribute('debits'));
        $credits = $this->money($totals?->getAttribute('credits'));

        return [
            'unbalanced_journals' => $this->unbalancedJournals()->count(),
            'total_debits' => $debits,
            'total_credits' => $credits,
            'difference' => abs($debits - $credits),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function checks(): Collection
    {
        $issues = collect();
        foreach ($this->unbalancedJournals() as $journal) {
            $issues->push($this->issue('error', 'Unbalanced posted journal', 'The stored journal header has unequal debit and credit totals.', $journal));
        }
        foreach ($this->journalsWithTooFewLines() as $journal) {
            $issues->push($this->issue('error', 'Insufficient journal lines', 'A posted journal has fewer than two lines.', $journal));
        }
        foreach ($this->invalidLines('both') as $line) {
            $issues->push($this->issue('error', 'Line has both debit and credit', 'A posted journal line must have exactly one accounting side.', $line->journalEntry, $line));
        }
        foreach ($this->invalidLines('neither') as $line) {
            $issues->push($this->issue('error', 'Line has no debit or credit', 'A posted journal line must have a positive debit or credit amount.', $line->journalEntry, $line));
        }
        foreach ($this->linesWithInactiveAccounts() as $line) {
            $issues->push($this->issue('warning', 'Inactive account used by posted history', 'Historical activity remains valid, but the account is currently inactive.', $line->journalEntry, $line));
        }
        foreach ($this->duplicateSources() as $source) {
            $issues->push($this->issue('warning', 'Potential duplicate source posting', 'More than one posted journal references the same source and event. Review before making corrections.', null, null, $source));
        }
        foreach ($this->reversalIssues() as $issue) {
            $issues->push($issue);
        }
        foreach ($this->openingBalanceIssues() as $issue) {
            $issues->push($issue);
        }

        $summary = $this->summary();
        if ($summary['difference'] !== 0) {
            $issues->push($this->issue('error', 'Ledger debit and credit difference', 'All posted journal lines do not net to zero. No automatic correction has been made.', null, null, $summary));
        }

        return $issues->values();
    }

    /** @return Collection<int, JournalEntry> */
    private function unbalancedJournals(): Collection
    {
        return JournalEntry::query()
            ->whereIn('status', self::POSTED_STATUSES)
            ->whereColumn('total_debit', '!=', 'total_credit')
            ->orderByDesc('transaction_date')
            ->get();
    }

    /** @return Collection<int, JournalEntry> */
    private function journalsWithTooFewLines(): Collection
    {
        return JournalEntry::query()
            ->whereIn('status', self::POSTED_STATUSES)
            ->has('lines', '<', 2)
            ->get();
    }

    /** @return Collection<int, JournalEntryLine> */
    private function invalidLines(string $kind): Collection
    {
        return JournalEntryLine::query()
            ->with('journalEntry')
            ->whereHas('journalEntry', fn ($query) => $query->whereIn('status', self::POSTED_STATUSES))
            ->when($kind === 'both', fn ($query) => $query->where('debit', '>', 0)->where('credit', '>', 0))
            ->when($kind === 'neither', fn ($query) => $query->where('debit', '<=', 0)->where('credit', '<=', 0))
            ->get();
    }

    /** @return Collection<int, JournalEntryLine> */
    private function linesWithInactiveAccounts(): Collection
    {
        return JournalEntryLine::query()
            ->with('journalEntry')
            ->whereHas('journalEntry', fn ($query) => $query->whereIn('status', self::POSTED_STATUSES))
            ->whereHas('account', fn ($query) => $query->where('is_active', false))
            ->get();
    }

    /** @return Collection<int, object> */
    private function duplicateSources(): Collection
    {
        return JournalEntry::query()
            ->whereIn('status', self::POSTED_STATUSES)
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->where('source_type', '!=', 'journal_reversal')
            ->select('source_type', 'source_id', 'source_event', DB::raw('COUNT(*) as occurrence_count'))
            ->groupBy('source_type', 'source_id', 'source_event')
            ->having('occurrence_count', '>', 1)
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function reversalIssues(): Collection
    {
        $issues = collect();
        $reversed = JournalEntry::query()->where('status', 'reversed')->get();
        foreach ($reversed as $journal) {
            if (! $journal->reversal_entry_id || ! JournalEntry::query()->whereKey($journal->reversal_entry_id)->where('status', 'posted')->exists()) {
                $issues->push($this->issue('error', 'Reversed journal is missing a posted reversal', 'The original journal is marked reversed without a valid posted reversal entry.', $journal));
            }
        }

        $orphanedReversals = JournalEntry::query()
            ->where('source_type', 'journal_reversal')
            ->whereNotNull('source_id')
            ->whereIn('status', self::POSTED_STATUSES)
            ->get()
            ->filter(fn (JournalEntry $journal) => ! JournalEntry::query()->whereKey($journal->source_id)->exists());
        foreach ($orphanedReversals as $journal) {
            $issues->push($this->issue('error', 'Reversal entry is missing its original journal', 'The reversal source reference no longer resolves to an original journal entry.', $journal));
        }

        return $issues;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function openingBalanceIssues(): Collection
    {
        $issues = collect();
        $accounts = AccountingAccount::query()->where('opening_balance', '!=', 0)->get();
        foreach ($accounts as $account) {
            $hasOpeningJournal = JournalEntryLine::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($query) => $query->whereIn('status', self::POSTED_STATUSES)->where('source_type', 'opening_balance'))
                ->exists();
            if ($hasOpeningJournal) {
                $issues->push($this->issue('warning', 'Opening balance may be counted twice', 'This account has a configured opening balance and a posted opening-balance journal. Confirm the chosen opening-balance method.', null, null, ['account' => $account->displayLabel()]));
            }
        }

        return $issues;
    }

    private function issue(string $severity, string $title, string $description, ?JournalEntry $journal = null, ?JournalEntryLine $line = null, mixed $context = null): array
    {
        return [
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'journal' => $journal,
            'line' => $line,
            'context' => $context,
        ];
    }

    private function money(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) return 0;
        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return $matches[1] === '-' ? -$cents : $cents;
    }
}
