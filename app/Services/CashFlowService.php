<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Direct-method Cash Flow reporting generated only from posted journal lines
 * and configured opening balances. All monetary results are integer sen.
 */
class CashFlowService
{
    private const POSTED_STATUSES = ['posted', 'reversed'];

    public function __construct(
        private readonly CashFlowConfigurationService $configuration,
        private readonly CashFlowClassificationService $classification,
    ) {}

    /** @param array<string, mixed> $filters */
    public function report(array $filters, int $perPage = 50): array
    {
        $this->configuration->ensureDefaults();
        $period = $this->period($filters);
        $accounts = $this->cashAccounts($filters);
        if ($accounts->isEmpty()) {
            return $this->emptyReport($period, $filters);
        }

        $allCashIds = AccountingAccount::query()->cashFlowEnabled()->pluck('id');
        $allMovements = $this->movements($accounts, $allCashIds, $period);
        $display = $this->applyFilters($allMovements, $filters)->values();
        $consolidated = blank($filters['cash_account_id'] ?? null);

        $opening = $accounts->sum(fn (AccountingAccount $account): int => $this->openingBalance($account, $period['start']));
        $positions = $this->cashPositions($accounts, $allMovements, $period);
        $statementMovements = $display->filter(fn (array $movement): bool => $this->isStatementMovement($movement, $consolidated));
        $sectionTotals = $this->sectionTotals($statementMovements);
        $cashIn = $statementMovements->sum('cash_in');
        $cashOut = $statementMovements->sum('cash_out');
        $net = $cashIn - $cashOut;
        $ledgerClosing = $positions->sum('closing_balance');
        $expectedClosing = $opening + $net;
        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $running = $opening;
        $withRunning = $display->sortBy($this->sortKey())->values()->map(function (array $movement) use (&$running, $consolidated): array {
            $running += $this->runningChange($movement, $consolidated);
            $movement['running_balance'] = $running;

            return $movement;
        });
        $paginator = new LengthAwarePaginator($withRunning->forPage($page, $perPage)->values(), $withRunning->count(), $perPage, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);

        return [
            'period' => $period,
            'filters' => $filters,
            'cash_accounts' => $accounts,
            'has_cash_accounts' => true,
            'opening_cash' => $opening,
            'cash_inflow' => $cashIn,
            'cash_outflow' => $cashOut,
            'net_cash_movement' => $net,
            'closing_cash' => $expectedClosing,
            'general_ledger_closing_cash' => $ledgerClosing,
            'reconciliation_difference' => $expectedClosing - $ledgerClosing,
            'sections' => $sectionTotals,
            'movements' => $paginator,
            'all_movements' => $allMovements,
            'filtered_movements' => $display,
            'positions' => $positions,
            'metrics' => [
                'operating' => $sectionTotals['operating']['net'],
                'investing' => $sectionTotals['investing']['net'],
                'financing' => $sectionTotals['financing']['net'],
                'refunds' => $this->categoryAmount($statementMovements, 'refunds'),
                'salary' => $this->categoryAmount($statementMovements, 'salary'),
                'drawings' => $this->categoryAmount($statementMovements, 'owner_drawings'),
                'capital' => $this->categoryAmount($statementMovements, 'owner_capital'),
                'fees' => $this->categoryAmount($statementMovements, 'payment_fees'),
                'unclassified' => $display->where('classification', 'unclassified')->sum(fn (array $movement): int => abs($movement['net_movement'])),
            ],
            'charts' => $this->charts($statementMovements, $period, $opening),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function account(AccountingAccount $account, array $filters, int $perPage = 50): array
    {
        $filters['cash_account_id'] = $account->id;

        return $this->report($filters, $perPage);
    }

    /** @param array<string, mixed> $filters */
    public function reconciliation(array $filters): array
    {
        $report = $this->report($filters, 100000);

        return [
            'report' => $report,
            'likely_causes' => collect([
                $report['reconciliation_difference'] !== 0 ? 'Filtered, unclassified, or non-cash-labelled journal activity can prevent a full reconciliation.' : null,
                $report['metrics']['unclassified'] > 0 ? 'One or more cash movements are awaiting a source-aware or account mapping.' : null,
                $report['cash_accounts']->contains(fn (AccountingAccount $account): bool => $account->is_clearing_account && ! $account->is_cash_equivalent) ? 'A clearing account is enabled without being marked as a cash equivalent.' : null,
            ])->filter()->values(),
        ];
    }

    /** @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    public function diagnostics(array $filters = []): Collection
    {
        $report = $this->report(array_merge(['range' => 'this_year', 'include_internal_transfers' => true, 'include_non_cash_activity' => true], $filters), 100000);
        $issues = collect();
        foreach ($report['filtered_movements']->where('classification', 'unclassified') as $movement) {
            $issues->push($this->issue('warning', 'Unclassified cash movement', 'This posted cash movement needs a source-aware or counter-account classification.', ['movement' => $movement]));
        }
        foreach ($report['all_movements']->filter(fn (array $movement): bool => $movement['is_internal_transfer'] && $movement['net_movement'] !== 0 && $movement['cash_account_id'] === $movement['counter_cash_account_id']) as $movement) {
            $issues->push($this->issue('error', 'Invalid internal transfer', 'A transfer was recorded against the same cash account on both sides and needs review.', $movement));
        }
        foreach (AccountingAccount::query()->where(fn ($query) => $query->where('is_cash_account', true)->orWhere('is_cash_equivalent', true))->where('cash_flow_enabled', false)->get() as $account) {
            $issues->push($this->issue('warning', 'Cash account not enabled', $account->displayLabel().' is marked as cash-like but excluded from Cash Flow reporting.', ['account' => $account]));
        }
        foreach ($report['positions']->filter(fn (array $position): bool => $position['account']->is_clearing_account && $position['closing_balance'] !== 0) as $position) {
            $issues->push($this->issue('warning', 'Clearing account carries a balance', $position['account']->displayLabel().' has an unreconciled closing balance.', ['account' => $position['account']]));
        }
        if ($report['reconciliation_difference'] !== 0) {
            $issues->push($this->issue('error', 'Cash Flow reconciliation difference', 'The direct-method cash-flow total does not equal the selected General Ledger cash closing balance.', []));
        }
        foreach (app(LedgerIntegrityService::class)->checks()->where('title', 'Unbalanced posted journal') as $issue) {
            $issues->push(['severity' => 'error', 'title' => 'Unbalanced posted journal', 'description' => $issue['description'], 'journal' => $issue['journal'], 'movement' => null]);
        }

        return $issues->values();
    }

    /** @param array<string, mixed> $filters */
    public function period(array $filters): array
    {
        $today = CarbonImmutable::today();
        $range = (string) ($filters['range'] ?? 'this_year');
        [$start, $end, $label] = match ($range) {
            'today' => [$today, $today, 'Today'],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek(), 'This Week'],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth(), 'This Month'],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), 'Last Month'],
            'this_quarter' => [$today->firstOfQuarter(), $today->lastOfQuarter(), 'This Quarter'],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear(), 'Last Year'],
            'custom' => [CarbonImmutable::parse((string) $filters['from_date'])->startOfDay(), CarbonImmutable::parse((string) $filters['to_date'])->endOfDay(), 'Custom Period'],
            default => [$today->startOfYear(), $today->endOfYear(), 'This Year'],
        };

        return compact('start', 'end', 'label', 'range');
    }

    /** @param array<string, mixed> $filters @return Collection<int, AccountingAccount> */
    public function cashAccounts(array $filters): Collection
    {
        return AccountingAccount::query()
            ->cashFlowEnabled()
            ->when(filled($filters['cash_account_id'] ?? null), fn ($query) => $query->whereKey($filters['cash_account_id']), fn ($query) => $query->where('is_active', true))
            ->when(! filter_var($filters['include_clearing'] ?? true, FILTER_VALIDATE_BOOL), fn ($query) => $query->where('is_clearing_account', false))
            ->orderBy('code')
            ->get();
    }

    /** @param Collection<int, AccountingAccount> $accounts @param Collection<int, int> $allCashIds @param array<string, mixed> $period @return Collection<int, array<string, mixed>> */
    private function movements(Collection $accounts, Collection $allCashIds, array $period): Collection
    {
        $includedIds = $accounts->pluck('id');
        $journals = JournalEntry::query()
            ->with(['lines.account', 'lines.ownerTransaction', 'lines.expense', 'poster'])
            ->whereIn('status', self::POSTED_STATUSES)
            ->whereBetween('transaction_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->whereHas('lines', fn ($query) => $query->whereIn('account_id', $includedIds))
            ->orderBy('transaction_date')->orderBy('posting_date')->orderBy('id')
            ->get();
        $movements = collect();
        foreach ($journals as $journal) {
            $allCashLines = $journal->lines->filter(fn (JournalEntryLine $line): bool => $allCashIds->contains($line->account_id));
            $cashLines = $journal->lines->filter(fn (JournalEntryLine $line): bool => $includedIds->contains($line->account_id));
            $counterLines = $journal->lines->reject(fn (JournalEntryLine $line): bool => $allCashIds->contains($line->account_id));
            $internal = $allCashLines->count() >= 2 && $counterLines->isEmpty();
            $counterCash = $allCashLines->pluck('account')->filter()->reject(fn (AccountingAccount $account): bool => $includedIds->contains($account->id));
            foreach ($cashLines as $line) {
                $net = $this->money($line->debit) - $this->money($line->credit);
                if ($net === 0) continue;
                $class = $this->classification->classify($journal, $counterLines, $internal);
                $movements->push($this->movementRecord($journal, $line, $counterLines, $counterCash, $net, $internal, $class));
            }
        }

        foreach ($accounts as $account) {
            if ($this->openingFallsWithinPeriod($account, $period)) {
                $amount = $this->money($account->opening_balance);
                if ($amount !== 0) $movements->push($this->openingRecord($account, $amount));
            }
        }

        return $movements->sortBy($this->sortKey())->values();
    }

    /** @param Collection<int, JournalEntryLine> $counterLines @param Collection<int, AccountingAccount> $counterCash @param array{classification:string,category:string,label:string} $class @return array<string, mixed> */
    private function movementRecord(JournalEntry $journal, JournalEntryLine $line, Collection $counterLines, Collection $counterCash, int $net, bool $internal, array $class): array
    {
        return [
            'row_type' => 'journal',
            'transaction_date' => $journal->transaction_date->toDateString(),
            'posting_date' => $journal->posting_date?->format('d M Y H:i'),
            'posting_sort' => $journal->posting_date?->format('Y-m-d H:i:s.u') ?? '0000-00-00 00:00:00.000000',
            'journal_id' => $journal->id,
            'line_id' => $line->id,
            'journal_number' => $journal->entry_number,
            'journal' => $journal,
            'source_type' => $journal->source_type ?: 'manual_journal',
            'source_label' => $journal->sourceLabel(),
            'reference' => $journal->reference,
            'cash_account_id' => $line->account_id,
            'cash_account' => $line->account,
            'counter_account' => $counterLines->pluck('account')->filter()->map(fn (AccountingAccount $account) => $account->displayLabel())->implode(', ') ?: $counterCash->map(fn (AccountingAccount $account) => $account->displayLabel())->implode(', '),
            'counter_cash_account_id' => $counterCash->first()?->id,
            'classification' => $class['classification'],
            'category' => $class['category'],
            'category_label' => $class['label'],
            'description' => $line->description ?: $journal->description,
            'cash_in' => $net > 0 ? $net : 0,
            'cash_out' => $net < 0 ? abs($net) : 0,
            'net_movement' => $net,
            'transfer_in' => $internal && $net > 0 ? $net : 0,
            'transfer_out' => $internal && $net < 0 ? abs($net) : 0,
            'payment_method' => $line->ownerTransaction?->payment_method ?: $line->expense?->payment_method,
            'posted_by' => $journal->poster?->name,
            'status' => $journal->status,
            'is_internal_transfer' => $internal,
            'is_non_cash' => $class['classification'] === 'non_cash',
        ];
    }

    /** @return array<string, mixed> */
    private function openingRecord(AccountingAccount $account, int $amount): array
    {
        return [
            'row_type' => 'opening', 'transaction_date' => $account->opening_balance_date?->toDateString(), 'posting_date' => null,
            'posting_sort' => '0000-00-00 00:00:00.000000', 'journal_id' => 0, 'line_id' => 0, 'journal_number' => 'Opening balance', 'journal' => null,
            'source_type' => 'opening_balance', 'source_label' => 'Opening balance configuration', 'reference' => null, 'cash_account_id' => $account->id, 'cash_account' => $account,
            'counter_account' => 'Approved opening balance', 'counter_cash_account_id' => null, 'classification' => 'unclassified', 'category' => 'unclassified',
            'category_label' => 'Opening Balance Configuration', 'description' => 'Configured account opening balance', 'cash_in' => $amount > 0 ? $amount : 0,
            'cash_out' => $amount < 0 ? abs($amount) : 0, 'net_movement' => $amount, 'transfer_in' => 0, 'transfer_out' => 0,
            'payment_method' => null, 'posted_by' => null, 'status' => 'opening', 'is_internal_transfer' => false, 'is_non_cash' => false,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $movements @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    private function applyFilters(Collection $movements, array $filters): Collection
    {
        return $movements->filter(function (array $movement) use ($filters): bool {
            if (filled($filters['classification'] ?? null) && $movement['classification'] !== $filters['classification']) return false;
            if (filled($filters['category'] ?? null) && $movement['category'] !== $filters['category']) return false;
            if (filled($filters['payment_method'] ?? null) && strcasecmp((string) $movement['payment_method'], (string) $filters['payment_method']) !== 0) return false;
            if (filled($filters['source_type'] ?? null) && $movement['source_type'] !== $filters['source_type']) return false;
            if (filled($filters['journal_number'] ?? null) && ! str_contains(strtolower($movement['journal_number']), strtolower((string) $filters['journal_number']))) return false;
            if (filled($filters['reference'] ?? null) && ! str_contains(strtolower((string) $movement['reference']), strtolower((string) $filters['reference']))) return false;
            $amount = abs($movement['net_movement']);
            if (filled($filters['minimum_amount'] ?? null) && $amount < $this->money($filters['minimum_amount'])) return false;
            if (filled($filters['maximum_amount'] ?? null) && $amount > $this->money($filters['maximum_amount'])) return false;
            if (! filter_var($filters['include_internal_transfers'] ?? true, FILTER_VALIDATE_BOOL) && $movement['is_internal_transfer']) return false;
            if (! filter_var($filters['include_non_cash_activity'] ?? false, FILTER_VALIDATE_BOOL) && $movement['is_non_cash']) return false;
            if (filled($filters['search'] ?? null)) {
                $haystack = strtolower(implode(' ', [$movement['description'], $movement['counter_account'], $movement['source_label'], $movement['reference'], $movement['journal_number']]));
                if (! str_contains($haystack, strtolower(trim((string) $filters['search'])))) return false;
            }
            return true;
        });
    }

    /** @param Collection<int, array<string, mixed>> $movements @return array<string, array{label:string,rows:Collection<int, array<string,mixed>>,net:int}> */
    private function sectionTotals(Collection $movements): array
    {
        $labels = ['operating' => 'Operating Activities', 'investing' => 'Investing Activities', 'financing' => 'Financing Activities'];
        $result = [];
        foreach ($labels as $classification => $label) {
            $rows = $movements->where('classification', $classification)->groupBy('category')->map(function (Collection $items): array {
                return ['label' => $items->first()['category_label'], 'inflow' => $items->sum('cash_in'), 'outflow' => $items->sum('cash_out'), 'net' => $items->sum('net_movement')];
            })->sortBy(fn (array $row) => $row['label'])->values();
            $result[$classification] = ['label' => $label, 'rows' => $rows, 'net' => $rows->sum('net')];
        }
        return $result;
    }

    /** @param Collection<int, AccountingAccount> $accounts @param Collection<int, array<string,mixed>> $movements @param array<string, mixed> $period @return Collection<int, array<string,mixed>> */
    private function cashPositions(Collection $accounts, Collection $movements, array $period): Collection
    {
        return $accounts->map(function (AccountingAccount $account) use ($movements, $period): array {
            $rows = $movements->where('cash_account_id', $account->id);
            $opening = $this->openingBalance($account, $period['start']);
            $actualMovement = $rows->sum('net_movement');
            return [
                'account' => $account, 'opening_balance' => $opening,
                'cash_in' => $rows->reject(fn (array $row): bool => $row['is_internal_transfer'] || $row['is_non_cash'])->sum('cash_in'),
                'cash_out' => $rows->reject(fn (array $row): bool => $row['is_internal_transfer'] || $row['is_non_cash'])->sum('cash_out'),
                'internal_transfers_in' => $rows->sum('transfer_in'), 'internal_transfers_out' => $rows->sum('transfer_out'),
                'net_movement' => $actualMovement, 'closing_balance' => $opening + $actualMovement,
                'last_activity' => $rows->sortByDesc($this->sortKey())->first()['transaction_date'] ?? null,
            ];
        });
    }

    /** @param array<string,mixed> $movement */
    private function isStatementMovement(array $movement, bool $consolidated): bool
    {
        if ($movement['is_non_cash']) return false;
        return ! ($consolidated && $movement['is_internal_transfer']);
    }

    /** @param array<string,mixed> $movement */
    private function runningChange(array $movement, bool $consolidated): int
    {
        return $consolidated && $movement['is_internal_transfer'] ? 0 : $movement['net_movement'];
    }

    /** @param Collection<int, array<string,mixed>> $movements */
    private function categoryAmount(Collection $movements, string $category): int
    {
        return $movements->where('category', $category)->sum(fn (array $movement): int => abs($movement['net_movement']));
    }

    /** @param Collection<int, array<string,mixed>> $movements @param array<string,mixed> $period */
    private function charts(Collection $movements, array $period, int $opening): array
    {
        $months = collect();
        for ($month = $period['start']->startOfMonth(); $month->lessThanOrEqualTo($period['end']->startOfMonth()); $month = $month->addMonth()) $months->put($month->format('Y-m'), ['label' => $month->format('M Y'), 'in' => 0, 'out' => 0, 'net' => 0]);
        foreach ($movements as $movement) {
            $key = CarbonImmutable::parse($movement['transaction_date'])->format('Y-m');
            if (! $months->has($key)) continue;
            $row = $months->get($key); $row['in'] += $movement['cash_in']; $row['out'] += $movement['cash_out']; $row['net'] += $movement['net_movement']; $months->put($key, $row);
        }
        $closing = $opening; $closingTrend = $months->map(function (array $row) use (&$closing): int { $closing += $row['net']; return $closing; });
        $sections = $this->sectionTotals($movements);
        return [
            'labels' => $months->pluck('label')->values(), 'inflow' => $months->pluck('in')->values(), 'outflow' => $months->pluck('out')->values(), 'net' => $months->pluck('net')->values(),
            'closing' => $closingTrend->values(), 'classifications' => ['labels' => ['Operating', 'Investing', 'Financing'], 'values' => [$sections['operating']['net'], $sections['investing']['net'], $sections['financing']['net']]],
            'outflow_categories' => $movements->filter(fn (array $movement) => $movement['cash_out'] > 0)->groupBy('category_label')->map(fn (Collection $rows) => $rows->sum('cash_out'))->sortDesc(),
        ];
    }

    private function openingBalance(AccountingAccount $account, CarbonImmutable $start): int
    {
        $baseline = 0;
        if ($account->hasOpeningBalance() && ! $this->hasPostedOpeningJournal($account) && (! $account->opening_balance_date || CarbonImmutable::parse($account->opening_balance_date)->lt($start->startOfDay()))) {
            $baseline = $this->money($account->opening_balance);
        }
        $prior = JournalEntryLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)->whereIn('journal_entries.status', self::POSTED_STATUSES)
            ->whereDate('journal_entries.transaction_date', '<', $start->toDateString())
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as debit, COALESCE(SUM(journal_entry_lines.credit), 0) as credit')->first();
        return $baseline + $this->money($prior?->debit) - $this->money($prior?->credit);
    }

    /** @param array<string,mixed> $period */
    private function openingFallsWithinPeriod(AccountingAccount $account, array $period): bool
    {
        return $account->hasOpeningBalance() && ! $this->hasPostedOpeningJournal($account) && $account->opening_balance_date
            && CarbonImmutable::parse($account->opening_balance_date)->betweenIncluded($period['start']->startOfDay(), $period['end']->startOfDay());
    }

    private function hasPostedOpeningJournal(AccountingAccount $account): bool
    {
        return JournalEntryLine::query()->where('account_id', $account->id)->whereHas('journalEntry', fn ($query) => $query->whereIn('status', self::POSTED_STATUSES)->where('source_type', 'opening_balance'))->exists();
    }

    /** @return callable(array<string,mixed>):string */
    private function sortKey(): callable
    {
        return fn (array $record): string => implode('|', [$record['transaction_date'] ?? '', $record['posting_sort'] ?? '', str_pad((string) ($record['journal_id'] ?? 0), 12, '0', STR_PAD_LEFT), str_pad((string) ($record['line_id'] ?? 0), 12, '0', STR_PAD_LEFT)]);
    }

    /** @param array<string,mixed> $period @param array<string,mixed> $filters */
    private function emptyReport(array $period, array $filters): array
    {
        $sections = collect(['operating', 'investing', 'financing'])->mapWithKeys(fn (string $key) => [$key => ['label' => ucfirst($key).' Activities', 'rows' => collect(), 'net' => 0]])->all();
        return ['period' => $period, 'filters' => $filters, 'cash_accounts' => collect(), 'has_cash_accounts' => false, 'opening_cash' => 0, 'cash_inflow' => 0, 'cash_outflow' => 0, 'net_cash_movement' => 0, 'closing_cash' => 0, 'general_ledger_closing_cash' => 0, 'reconciliation_difference' => 0, 'sections' => $sections, 'movements' => new LengthAwarePaginator([], 0, 50), 'all_movements' => collect(), 'filtered_movements' => collect(), 'positions' => collect(), 'metrics' => ['operating' => 0, 'investing' => 0, 'financing' => 0, 'refunds' => 0, 'salary' => 0, 'drawings' => 0, 'capital' => 0, 'fees' => 0, 'unclassified' => 0], 'charts' => ['labels' => [], 'inflow' => [], 'outflow' => [], 'net' => [], 'closing' => [], 'classifications' => ['labels' => [], 'values' => []], 'outflow_categories' => collect()]];
    }

    /** @param array<string,mixed> $movement @param array<string,mixed> $extra */
    private function issue(string $severity, string $title, string $description, array $extra = []): array
    {
        return ['severity' => $severity, 'title' => $title, 'description' => $description, 'journal' => $extra['journal'] ?? ($extra['movement']['journal'] ?? null), 'movement' => $extra['movement'] ?? null, 'account' => $extra['account'] ?? null];
    }

    private function money(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) return 0;
        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');
        return $matches[1] === '-' ? -$cents : $cents;
    }
}
