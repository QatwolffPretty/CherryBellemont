<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-only general-ledger reporting built from accounting accounts and valid
 * posted journal lines. Monetary values returned by this service are integer
 * sen so no ledger calculation relies on floating point arithmetic.
 */
class GeneralLedgerService
{
    /** @var array<int, string> */
    private const POSTED_STATUSES = ['posted', 'reversed'];

    /** @var array<string, string> */
    public const ACCOUNT_TYPES = [
        'asset' => 'Assets',
        'liability' => 'Liabilities',
        'equity' => 'Equity',
        'revenue' => 'Revenue',
        'cost_of_goods_sold' => 'Cost of Goods Sold',
        'expense' => 'Expenses',
    ];

    /** @var Collection<int, int>|null */
    private ?Collection $accountsWithPostedOpeningJournals = null;

    /** @param array<string, mixed> $filters */
    public function overview(array $filters): array
    {
        $period = $this->period($filters);
        $accounts = $this->filteredAccounts($filters)->with('parent:id,code,name')->orderBy('type')->orderBy('code')->get();
        $rows = $this->summaryRows($accounts, $period, $filters);
        $byType = $rows->groupBy(fn (array $row) => $row['account']->type);
        $integrity = app(LedgerIntegrityService::class)->summary();

        return [
            'period' => $period,
            'filters' => $filters,
            'accounts' => $accounts,
            'rows' => $rows,
            'metrics' => [
                'opening_assets' => $this->netTypeBalance($byType->get('asset', collect()), 'opening_balance'),
                'opening_liabilities' => $this->netTypeBalance($byType->get('liability', collect()), 'opening_balance'),
                'opening_equity' => $this->netTypeBalance($byType->get('equity', collect()), 'opening_balance'),
                'total_debits' => $rows->sum('total_debit'),
                'total_credits' => $rows->sum('total_credit'),
                'net_revenue_movement' => $this->netRevenueMovement($byType->get('revenue', collect())),
                'net_expense_movement' => $this->netTypeBalance($byType->get('expense', collect()), 'movement') + $this->netTypeBalance($byType->get('cost_of_goods_sold', collect()), 'movement'),
                'closing_assets' => $this->netTypeBalance($byType->get('asset', collect()), 'closing_balance'),
                'closing_liabilities' => $this->netTypeBalance($byType->get('liability', collect()), 'closing_balance'),
                'closing_equity' => $this->netTypeBalance($byType->get('equity', collect()), 'closing_balance'),
                'accounts_with_activity' => $rows->where('has_activity', true)->count(),
                'unbalanced_posted_journals' => $integrity['unbalanced_journals'],
            ],
            'integrity' => $integrity,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function accountLedger(AccountingAccount $account, array $filters, int $perPage = 50): array
    {
        $period = $this->period($filters);
        $opening = $this->openingBalance($account, $period['start']);
        $lines = $this->lineQuery()
            ->where('journal_entry_lines.account_id', $account->id)
            ->whereBetween('journal_entries.transaction_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('journal_entries.entry_number', 'like', '%'.$search.'%')
                        ->orWhere('journal_entries.reference', 'like', '%'.$search.'%')
                        ->orWhere('journal_entries.description', 'like', '%'.$search.'%')
                        ->orWhere('journal_entry_lines.description', 'like', '%'.$search.'%');
                });
            })
            ->when(filled($filters['source_type'] ?? null), fn (Builder $query) => $query->where('journal_entries.source_type', $filters['source_type']))
            ->when(($filters['movement'] ?? null) === 'debit', fn (Builder $query) => $query->where('journal_entry_lines.debit', '>', 0))
            ->when(($filters['movement'] ?? null) === 'credit', fn (Builder $query) => $query->where('journal_entry_lines.credit', '>', 0))
            ->get();

        $records = $lines->map(fn (JournalEntryLine $line) => $this->journalRecord($line, $account));
        if ($this->openingFallsWithinPeriod($account, $period)) {
            $records->push($this->openingRecord($account));
        }

        $records = $records
            ->sortBy(fn (array $record) => implode('|', [
                $record['transaction_date'],
                $record['posting_sort'],
                str_pad((string) $record['journal_id'], 12, '0', STR_PAD_LEFT),
                str_pad((string) $record['line_id'], 12, '0', STR_PAD_LEFT),
            ]))
            ->values();

        $running = $opening;
        $records = $records->map(function (array $record) use (&$running, $account): array {
            $running += $record['movement'];
            $record['running_balance'] = $running;
            $record['running_balance_label'] = $this->balanceLabel($running, $account);

            return $record;
        });

        $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
        $items = $records->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $records->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        $totalDebit = $lines->sum(fn (JournalEntryLine $line): int => $this->money($line->debit));
        $totalCredit = $lines->sum(fn (JournalEntryLine $line): int => $this->money($line->credit));
        $openingMovement = $this->openingFallsWithinPeriod($account, $period) ? $this->money($account->opening_balance) : 0;

        return [
            'period' => $period,
            'account' => $account,
            'opening_balance' => $opening,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'movement' => $this->movement($account, $totalDebit, $totalCredit) + $openingMovement,
            'closing_balance' => $running,
            'rows' => $paginator,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function trialBalance(array $filters): array
    {
        $overview = $this->overview($filters);
        $rows = $overview['rows']->map(function (array $row): array {
            $balance = $row['closing_balance'];
            $account = $row['account'];
            $isDebit = ($account->isDebitNormal() && $balance >= 0) || (! $account->isDebitNormal() && $balance < 0);

            return $row + [
                'debit_balance' => $isDebit ? abs($balance) : 0,
                'credit_balance' => $isDebit ? 0 : abs($balance),
            ];
        });

        return [
            'period' => $overview['period'],
            'rows' => $rows,
            'total_debit' => $rows->sum('debit_balance'),
            'total_credit' => $rows->sum('credit_balance'),
            'difference' => abs($rows->sum('debit_balance') - $rows->sum('credit_balance')),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function period(array $filters): array
    {
        $today = CarbonImmutable::today();
        $range = (string) ($filters['range'] ?? 'this_year');
        [$start, $end, $label] = match ($range) {
            'today' => [$today, $today, 'Today'],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek(), 'This Week'],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), 'Last Month'],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth(), 'This Month'],
            'this_quarter' => [$today->firstOfQuarter(), $today->lastOfQuarter(), 'This Quarter'],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear(), 'Last Year'],
            'custom' => [CarbonImmutable::parse((string) $filters['from_date'])->startOfDay(), CarbonImmutable::parse((string) $filters['to_date'])->endOfDay(), 'Custom Period'],
            default => [$today->startOfYear(), $today->endOfYear(), 'This Year'],
        };

        return compact('start', 'end', 'label', 'range');
    }

    /** @param array<string, mixed> $filters */
    public function accountsForFilters(array $filters): Collection
    {
        return $this->filteredAccounts($filters)->orderBy('code')->get();
    }

    public function balanceLabel(int $balance, AccountingAccount $account): string
    {
        if ($balance === 0) {
            return 'RM 0.00';
        }

        $normalSide = $account->isDebitNormal() ? 'Dr' : 'Cr';
        $oppositeSide = $account->isDebitNormal() ? 'Cr' : 'Dr';

        return 'RM '.number_format(abs($balance) / 100, 2).' '.($balance >= 0 ? $normalSide : $oppositeSide);
    }

    /** @param array<string, mixed> $filters */
    private function filteredAccounts(array $filters): Builder
    {
        return AccountingAccount::query()
            ->when(filled($filters['account_id'] ?? null), fn (Builder $query) => $query->whereKey($filters['account_id']))
            ->when(filled($filters['account_code'] ?? null), fn (Builder $query) => $query->where('code', 'like', '%'.trim((string) $filters['account_code']).'%'))
            ->when(filled($filters['account_type'] ?? null), fn (Builder $query) => $query->where('type', $filters['account_type']))
            ->when(filled($filters['account_subtype'] ?? null), fn (Builder $query) => $query->where('subtype', $filters['account_subtype']))
            ->when(($filters['status'] ?? 'all') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? 'all') === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when(($filters['kind'] ?? 'all') === 'system', fn (Builder $query) => $query->where('is_system', true))
            ->when(($filters['kind'] ?? 'all') === 'custom', fn (Builder $query) => $query->where('is_system', false))
            ->when(filled($filters['normal_balance'] ?? null), fn (Builder $query) => $query->where('normal_balance', $filters['normal_balance']))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(fn (Builder $nested) => $nested->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'));
            });
    }

    /** @param Collection<int, AccountingAccount> $accounts @param array<string, mixed> $period @param array<string, mixed> $filters */
    private function summaryRows(Collection $accounts, array $period, array $filters): Collection
    {
        if ($accounts->isEmpty()) {
            return collect();
        }

        $ids = $accounts->pluck('id');
        $before = $this->aggregateLines($ids, null, $period['start']->toDateString());
        $within = $this->aggregateLines($ids, $period['start']->toDateString(), $period['end']->toDateString());

        return $accounts->map(function (AccountingAccount $account) use ($before, $within, $period): array {
            $prior = $before->get($account->id, ['debit' => 0, 'credit' => 0]);
            $activity = $within->get($account->id, ['debit' => 0, 'credit' => 0, 'last_activity' => null]);
            $opening = $this->openingBaselineBefore($account, $period['start']) + $this->movement($account, $prior['debit'], $prior['credit']);
            $openingMovement = $this->openingFallsWithinPeriod($account, $period) ? $this->money($account->opening_balance) : 0;
            $movement = $this->movement($account, $activity['debit'], $activity['credit']) + $openingMovement;
            $closing = $opening + $movement;

            return [
                'account' => $account,
                'opening_balance' => $opening,
                'total_debit' => $activity['debit'],
                'total_credit' => $activity['credit'],
                'movement' => $movement,
                'closing_balance' => $closing,
                'opening_label' => $this->balanceLabel($opening, $account),
                'movement_label' => $this->balanceLabel($movement, $account),
                'closing_label' => $this->balanceLabel($closing, $account),
                'last_activity' => $activity['last_activity'],
                'has_activity' => $activity['debit'] > 0 || $activity['credit'] > 0 || $openingMovement !== 0,
            ];
        })->filter(function (array $row) use ($filters): bool {
            if (($filters['activity'] ?? 'all') === 'with' && ! $row['has_activity']) return false;
            if (($filters['activity'] ?? 'all') === 'without' && $row['has_activity']) return false;
            if (filled($filters['min_closing'] ?? null) && $row['closing_balance'] < $this->money($filters['min_closing'])) return false;
            if (filled($filters['max_closing'] ?? null) && $row['closing_balance'] > $this->money($filters['max_closing'])) return false;

            return true;
        })->values();
    }

    /** @param Collection<int, int> $accountIds */
    private function aggregateLines(Collection $accountIds, ?string $start, string $end): Collection
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->whereIn('journal_entries.status', self::POSTED_STATUSES)
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as debit, SUM(journal_entry_lines.credit) as credit, MAX(journal_entries.transaction_date) as last_activity')
            ->groupBy('journal_entry_lines.account_id');

        if ($start === null) {
            $query->whereDate('journal_entries.transaction_date', '<', $end);
        } else {
            $query->whereBetween('journal_entries.transaction_date', [$start, $end]);
        }

        return $query->get()->mapWithKeys(fn (JournalEntryLine $line): array => [$line->account_id => [
            'debit' => $this->money($line->debit),
            'credit' => $this->money($line->credit),
            'last_activity' => $line->getAttribute('last_activity'),
        ]]);
    }

    private function openingBalance(AccountingAccount $account, CarbonImmutable $start): int
    {
        $prior = $this->aggregateLines(collect([$account->id]), null, $start->toDateString())->get($account->id, ['debit' => 0, 'credit' => 0]);

        return $this->openingBaselineBefore($account, $start) + $this->movement($account, $prior['debit'], $prior['credit']);
    }

    /** @param array<string, mixed> $period */
    private function openingFallsWithinPeriod(AccountingAccount $account, array $period): bool
    {
        if ($this->hasPostedOpeningJournal($account) || ! $account->hasOpeningBalance() || ! $account->opening_balance_date) {
            return false;
        }

        $date = CarbonImmutable::parse($account->opening_balance_date)->startOfDay();

        return $date->betweenIncluded($period['start']->startOfDay(), $period['end']->startOfDay());
    }

    private function openingBaselineBefore(AccountingAccount $account, CarbonImmutable $start): int
    {
        if ($this->hasPostedOpeningJournal($account) || ! $account->hasOpeningBalance() || ! $account->opening_balance_date) {
            return $account->hasOpeningBalance() ? $this->money($account->opening_balance) : 0;
        }

        return CarbonImmutable::parse($account->opening_balance_date)->lt($start->startOfDay())
            ? $this->money($account->opening_balance)
            : 0;
    }

    private function lineQuery(): Builder
    {
        return JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', self::POSTED_STATUSES)
            ->with(['journalEntry.poster:id,name', 'account:id,code,name,type,subtype,normal_balance,is_active', 'order:id,order_number,number', 'expense:id,expense_number', 'ownerTransaction:id,transaction_number']);
    }

    private function journalRecord(JournalEntryLine $line, AccountingAccount $account): array
    {
        $entry = $line->journalEntry;
        $debit = $this->money($line->debit);
        $credit = $this->money($line->credit);

        return [
            'row_type' => 'journal',
            'transaction_date' => $entry->transaction_date?->toDateString() ?? '',
            'posting_date' => $entry->posting_date?->format('d M Y H:i'),
            'posting_sort' => $entry->posting_date?->format('Y-m-d H:i:s.u') ?? '0000-00-00 00:00:00.000000',
            'journal_id' => $entry->id,
            'line_id' => $line->id,
            'journal_number' => $entry->entry_number,
            'journal' => $entry,
            'reference' => $entry->reference,
            'source' => $this->source($line),
            'description' => $entry->description,
            'line_description' => $line->description,
            'debit' => $debit,
            'credit' => $credit,
            'movement' => $this->movement($account, $debit, $credit),
            'status' => $entry->status,
            'posted_by' => $entry->poster?->name,
        ];
    }

    private function openingRecord(AccountingAccount $account): array
    {
        $amount = $this->money($account->opening_balance);

        return [
            'row_type' => 'opening',
            'transaction_date' => $account->opening_balance_date?->toDateString() ?? '',
            'posting_date' => null,
            'posting_sort' => '0000-00-00 00:00:00.000000',
            'journal_id' => 0,
            'line_id' => 0,
            'journal_number' => 'Opening balance',
            'journal' => null,
            'reference' => null,
            'source' => ['label' => 'Opening balance configuration', 'url' => null],
            'description' => 'Configured account opening balance',
            'line_description' => null,
            'debit' => $account->isDebitNormal() ? $amount : 0,
            'credit' => $account->isDebitNormal() ? 0 : $amount,
            'movement' => $amount,
            'status' => 'opening',
            'posted_by' => null,
        ];
    }

    /** @return array{label:string,url:?string} */
    private function source(JournalEntryLine $line): array
    {
        $entry = $line->journalEntry;
        if ($entry->source_type === 'journal_reversal') {
            return ['label' => 'Reversal of journal entry', 'url' => $entry->source_id ? route('admin.accounting.journals.show', $entry->source_id) : null];
        }
        if ($line->order) {
            return ['label' => 'Order '.($line->order->order_number ?: $line->order->number), 'url' => route('admin.orders.show', $line->order)];
        }
        if ($line->expense) {
            return ['label' => 'Expense '.$line->expense->expense_number, 'url' => route('admin.accounting.expenses.edit', $line->expense)];
        }
        if ($line->ownerTransaction) {
            return ['label' => 'Owner transaction '.$line->ownerTransaction->transaction_number, 'url' => route('admin.accounting.owner-transactions.edit', $line->ownerTransaction)];
        }

        return ['label' => $entry->sourceLabel(), 'url' => null];
    }

    private function movement(AccountingAccount $account, int $debit, int $credit): int
    {
        return $account->isDebitNormal() ? $debit - $credit : $credit - $debit;
    }

    private function hasPostedOpeningJournal(AccountingAccount $account): bool
    {
        if ($this->accountsWithPostedOpeningJournals === null) {
            $this->accountsWithPostedOpeningJournals = JournalEntryLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->whereIn('journal_entries.status', self::POSTED_STATUSES)
                ->where('journal_entries.source_type', 'opening_balance')
                ->distinct()
                ->pluck('journal_entry_lines.account_id');
        }

        return $this->accountsWithPostedOpeningJournals->contains($account->id);
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function netRevenueMovement(Collection $rows): int
    {
        return $this->netTypeBalance($rows, 'movement');
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function netTypeBalance(Collection $rows, string $key): int
    {
        return $rows->sum(function (array $row) use ($key): int {
            $expectedDebit = in_array($row['account']->type, ['asset', 'expense', 'cost_of_goods_sold'], true);
            $isNormalSide = $expectedDebit === $row['account']->isDebitNormal();

            return $isNormalSide ? $row[$key] : -$row[$key];
        });
    }

    private function money(mixed $amount): int
    {
        if (is_int($amount)) return $amount;
        $value = trim((string) ($amount ?? '0'));
        if ($value === '') return 0;
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) return 0;
        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return $matches[1] === '-' ? -$cents : $cents;
    }
}
