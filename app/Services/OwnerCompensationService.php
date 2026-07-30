<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\OwnerTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Owns draft data, filtering and read-only owner-compensation summaries. */
class OwnerCompensationService
{
    public function __construct(
        private readonly AccountingAccountService $accounts,
        private readonly GeneralLedgerService $ledger,
        private readonly AccountingAuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(array $data, int $userId, ?string $attachmentPath = null, ?string $ip = null): OwnerTransaction
    {
        $this->accounts->ensureDefaults();
        $posting = $this->postingAccounts((string) $data['transaction_type'], $data['payment_account_id'] ?? null);

        return DB::transaction(function () use ($data, $userId, $attachmentPath, $posting, $ip): OwnerTransaction {
            $transaction = OwnerTransaction::query()->create([
                'transaction_number' => 'PENDING-'.uniqid('', true),
                'transaction_date' => $data['transaction_date'],
                'transaction_type' => $data['transaction_type'],
                'amount' => $data['amount'],
                'payment_account_id' => $posting['payment']?->id,
                'destination_account_id' => $posting['destination']->id,
                'debit_account_id' => $posting['debit']->id,
                'credit_account_id' => $posting['credit']->id,
                'description' => $data['description'],
                'payment_method' => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'attachment_path' => $attachmentPath,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $transaction->update(['transaction_number' => $this->transactionNumber($transaction)]);
            $this->audit->record('owner_compensation.created', $transaction, $userId, [], ['status' => 'draft', 'type' => $transaction->transaction_type], $ip);

            return $transaction->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(OwnerTransaction $transaction, array $data, int $userId, ?string $attachmentPath = null, ?string $ip = null): OwnerTransaction
    {
        if (! $transaction->mayBePosted()) {
            throw ValidationException::withMessages(['transaction' => 'Only draft owner compensation records may be edited.']);
        }

        $posting = $this->postingAccounts((string) $data['transaction_type'], $data['payment_account_id'] ?? null);

        return DB::transaction(function () use ($transaction, $data, $userId, $attachmentPath, $posting, $ip): OwnerTransaction {
            $transaction = OwnerTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if (! $transaction->mayBePosted()) {
                throw ValidationException::withMessages(['transaction' => 'Only draft owner compensation records may be edited.']);
            }
            $old = $transaction->only(['transaction_date', 'transaction_type', 'amount', 'payment_account_id', 'description', 'reference_number']);
            $transaction->update([
                'transaction_date' => $data['transaction_date'],
                'transaction_type' => $data['transaction_type'],
                'amount' => $data['amount'],
                'payment_account_id' => $posting['payment']?->id,
                'destination_account_id' => $posting['destination']->id,
                'debit_account_id' => $posting['debit']->id,
                'credit_account_id' => $posting['credit']->id,
                'description' => $data['description'],
                'payment_method' => $data['payment_method'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'attachment_path' => $attachmentPath ?: $transaction->attachment_path,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $userId,
            ]);
            $this->audit->record('owner_compensation.updated', $transaction, $userId, $old, $transaction->only(array_keys($old)), $ip);

            return $transaction->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $period = $this->period($filters);

        return $this->filteredQuery($filters, $period)
            ->with(['paymentAccount:id,code,name', 'debitAccount:id,code,name', 'creditAccount:id,code,name', 'journalEntry:id,entry_number', 'creator:id,name', 'poster:id,name'])
            ->latest('transaction_date')->latest('id')
            ->paginate(25)->withQueryString();
    }

    /** @param array<string, mixed> $filters @return Collection<int, OwnerTransaction> */
    public function exportTransactions(array $filters): Collection
    {
        $period = $this->period($filters);

        return $this->filteredQuery($filters, $period)
            ->with(['paymentAccount:id,code,name', 'journalEntry:id,entry_number', 'creator:id,name', 'poster:id,name'])
            ->latest('transaction_date')->latest('id')->get();
    }

    /** @return array<string, array{debit:string,credit:string}> */
    public function mappingPreview(): array
    {
        $this->accounts->ensureDefaults();

        return [
            'salary' => ['debit' => $this->mapped('owner_salary_account')->displayLabel(), 'credit' => 'Selected Cash or Bank account'],
            'drawing' => ['debit' => $this->mapped('owner_drawings_account')->displayLabel(), 'credit' => 'Selected Cash or Bank account'],
            'capital_contribution' => ['debit' => 'Selected Cash or Bank account', 'credit' => $this->mapped('owner_capital_account')->displayLabel()],
            'business_reserve' => ['debit' => $this->mapped('retained_earnings_account')->displayLabel(), 'credit' => $this->mapped('business_reserve_account')->displayLabel()],
            'emergency_reserve' => ['debit' => $this->mapped('retained_earnings_account')->displayLabel(), 'credit' => $this->mapped('emergency_reserve_account')->displayLabel()],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function overview(array $filters = []): array
    {
        $today = CarbonImmutable::today();
        $month = ['start' => $today->startOfMonth(), 'end' => $today->endOfMonth()];
        $year = ['start' => $today->startOfYear(), 'end' => $today->endOfYear()];

        return [
            'salary_month' => $this->totalFor('salary', $month),
            'salary_year' => $this->totalFor('salary', $year),
            'drawings_month' => $this->totalFor('drawing', $month),
            'drawings_year' => $this->totalFor('drawing', $year),
            'capital_year' => $this->totalFor('capital_contribution', $year),
            'business_reserve_balance' => $this->mappedBalance('business_reserve_account'),
            'emergency_reserve_balance' => $this->mappedBalance('emergency_reserve_account'),
            'draft' => OwnerTransaction::query()->where('status', 'draft')->count(),
            'posted' => OwnerTransaction::query()->where('status', 'posted')->count(),
            'reversed' => OwnerTransaction::query()->where('status', 'reversed')->count(),
        ];
    }

    /** @return array{debit:AccountingAccount,credit:AccountingAccount,destination:AccountingAccount,payment:?AccountingAccount} */
    public function postingAccounts(string $type, mixed $paymentAccountId): array
    {
        $type = OwnerTransaction::LEGACY_TYPES[$type] ?? $type;
        $payment = filled($paymentAccountId) ? AccountingAccount::query()->active()->find($paymentAccountId) : null;
        $needsPayment = in_array($type, ['salary', 'drawing', 'capital_contribution'], true);
        if ($needsPayment && ! $payment) {
            throw ValidationException::withMessages(['payment_account_id' => 'Choose an active cash or bank account.']);
        }
        if ($payment && ($payment->type !== 'asset' || ! in_array($payment->subtype, ['Cash', 'Bank'], true))) {
            throw ValidationException::withMessages(['payment_account_id' => 'Owner compensation may only use an active Cash or Bank asset account.']);
        }

        return match ($type) {
            'salary' => $this->pair($this->mapped('owner_salary_account'), $payment, $payment, $this->mapped('owner_salary_account')),
            'drawing' => $this->pair($this->mapped('owner_drawings_account'), $payment, $payment, $this->mapped('owner_drawings_account')),
            'capital_contribution' => $this->pair($payment, $this->mapped('owner_capital_account'), $payment, $this->mapped('owner_capital_account')),
            'business_reserve' => $this->pair($this->mapped('retained_earnings_account'), $this->mapped('business_reserve_account'), null, $this->mapped('business_reserve_account')),
            'emergency_reserve' => $this->pair($this->mapped('retained_earnings_account'), $this->mapped('emergency_reserve_account'), null, $this->mapped('emergency_reserve_account')),
            default => throw ValidationException::withMessages(['transaction_type' => 'This owner compensation type is not supported.']),
        };
    }

    public function balanceAsOf(AccountingAccount $account, CarbonImmutable|string|null $date = null): int
    {
        $asOf = $date ? CarbonImmutable::parse($date)->toDateString() : CarbonImmutable::today()->toDateString();

        return $this->ledger->accountLedger($account, ['range' => 'custom', 'from_date' => '1900-01-01', 'to_date' => $asOf], 100000)['closing_balance'];
    }

    /** @return array<int, string> */
    public function postingWarnings(OwnerTransaction $transaction): array
    {
        if (! $transaction->mayBePosted()) {
            return [];
        }

        try {
            $accounts = $this->postingAccounts($transaction->canonicalType(), $transaction->payment_account_id);
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->all();
        }

        $amount = $this->decimalToCents((string) $transaction->amount);
        if (in_array($transaction->canonicalType(), ['salary', 'drawing'], true)) {
            $balance = $this->balanceAsOf($accounts['credit'], $transaction->transaction_date);
            if ($balance < $amount) {
                return [$transaction->canonicalType() === 'drawing'
                    ? 'Posting is blocked: this drawing exceeds the available posted Cash or Bank balance.'
                    : 'Warning: this salary payment exceeds the current posted Cash or Bank balance.'];
            }
        }
        if (in_array($transaction->canonicalType(), ['business_reserve', 'emergency_reserve'], true)) {
            $balance = $this->balanceAsOf($accounts['debit'], $transaction->transaction_date);
            if ($balance < $amount) {
                return ['Posting is blocked: this reserve allocation exceeds the available posted Retained Earnings balance.'];
            }
        }

        return [];
    }

    /** @param array<string, mixed> $filters */
    public function period(array $filters): array
    {
        $today = CarbonImmutable::today();
        [$start, $end, $label] = match ($filters['range'] ?? 'this_year') {
            'today' => [$today, $today, 'Today'],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth(), 'This Month'],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), 'Last Month'],
            'this_quarter' => [$today->firstOfQuarter(), $today->lastOfQuarter(), 'This Quarter'],
            'custom' => [CarbonImmutable::parse((string) $filters['from_date'])->startOfDay(), CarbonImmutable::parse((string) $filters['to_date'])->endOfDay(), 'Custom Period'],
            default => [$today->startOfYear(), $today->endOfYear(), 'This Year'],
        };

        return compact('start', 'end', 'label');
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters, array $period): Builder
    {
        return OwnerTransaction::query()
            ->whereBetween('transaction_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->when(filled($filters['transaction_type'] ?? null), fn (Builder $query) => $query->whereIn('transaction_type', $this->storedTypes((string) $filters['transaction_type'])))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['payment_account_id'] ?? null), fn (Builder $query) => $query->where('payment_account_id', $filters['payment_account_id']))
            ->when(filled($filters['payment_method'] ?? null), fn (Builder $query) => $query->where('payment_method', 'like', '%'.trim((string) $filters['payment_method']).'%'))
            ->when(filled($filters['reference'] ?? null), fn (Builder $query) => $query->where('reference_number', 'like', '%'.trim((string) $filters['reference']).'%'))
            ->when(filled($filters['transaction_number'] ?? null), fn (Builder $query) => $query->where('transaction_number', 'like', '%'.trim((string) $filters['transaction_number']).'%'))
            ->when(filled($filters['created_by'] ?? null), fn (Builder $query) => $query->where('created_by', $filters['created_by']))
            ->when(filled($filters['posted_by'] ?? null), fn (Builder $query) => $query->where('posted_by', $filters['posted_by']))
            ->when(filled($filters['minimum_amount'] ?? null), fn (Builder $query) => $query->where('amount', '>=', $filters['minimum_amount']))
            ->when(filled($filters['maximum_amount'] ?? null), fn (Builder $query) => $query->where('amount', '<=', $filters['maximum_amount']))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(fn (Builder $nested) => $nested->where('transaction_number', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')->orWhere('reference_number', 'like', '%'.$search.'%'));
            });
    }

    /** @return array{debit:AccountingAccount,credit:AccountingAccount,destination:AccountingAccount,payment:?AccountingAccount} */
    private function pair(?AccountingAccount $debit, ?AccountingAccount $credit, ?AccountingAccount $payment, ?AccountingAccount $destination): array
    {
        if (! $debit || ! $credit) {
            throw ValidationException::withMessages(['accounts' => 'A required Chart of Accounts mapping is missing or inactive. Review Financial Settings before posting.']);
        }

        return ['debit' => $debit, 'credit' => $credit, 'destination' => $destination ?: $debit, 'payment' => $payment];
    }

    private function mapped(string $key): AccountingAccount
    {
        try {
            return $this->accounts->mapped($key);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['accounts' => 'A required Chart of Accounts mapping is missing or inactive. Review Financial Settings before posting.']);
        }
    }

    /** @param array{start:CarbonImmutable,end:CarbonImmutable} $period */
    private function totalFor(string $type, array $period): int
    {
        return $this->decimalToCents((string) OwnerTransaction::query()
            ->where('status', 'posted')
            ->whereIn('transaction_type', $this->storedTypes($type))
            ->whereBetween('transaction_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->sum('amount'));
    }

    private function mappedBalance(string $setting): int
    {
        try {
            return $this->balanceAsOf($this->mapped($setting));
        } catch (ValidationException) {
            return 0;
        }
    }

    /** @return array<int, string> */
    private function storedTypes(string $canonical): array
    {
        return array_values(array_unique([$canonical, ...array_keys(array_filter(OwnerTransaction::LEGACY_TYPES, fn (string $value) => $value === $canonical))]));
    }

    private function transactionNumber(OwnerTransaction $transaction): string
    {
        return 'OC-'.$transaction->transaction_date->format('Y').'-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
    }

    private function decimalToCents(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
