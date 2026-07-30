<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\OwnerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Posts owner compensation through the existing balanced journal lifecycle. */
class OwnerCompensationPostingService
{
    public function __construct(
        private readonly OwnerCompensationService $ownerCompensation,
        private readonly JournalPostingService $journals,
        private readonly AccountingAuditService $audit,
    ) {}

    public function post(OwnerTransaction $transaction, ?int $userId, ?string $ip = null): JournalEntry
    {
        return DB::transaction(function () use ($transaction, $userId, $ip): JournalEntry {
            $transaction = OwnerTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->journal_entry_id && $transaction->status === 'posted') {
                throw ValidationException::withMessages(['transaction' => 'This owner compensation record has already been posted and is immutable.']);
            }
            if (! $transaction->mayBePosted()) {
                throw ValidationException::withMessages(['transaction' => 'Only draft owner compensation records can be posted.']);
            }

            $accounts = $this->ownerCompensation->postingAccounts($transaction->canonicalType(), $transaction->payment_account_id);
            $this->assertBalanceProtection($transaction, $accounts);
            $amount = $this->decimal($transaction->amount);
            $entry = $this->journals->createDraft([
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'source_type' => 'owner_compensation',
                'source_id' => $transaction->id,
                'source_event' => 'posted',
                'reference' => $transaction->reference_number ?: $transaction->transaction_number,
                'description' => $this->journalDescription($transaction),
                'currency' => 'MYR',
            ], [
                ['account_id' => $accounts['debit']->id, 'description' => $transaction->description, 'debit' => $amount, 'credit' => '0.00', 'owner_transaction_id' => $transaction->id],
                ['account_id' => $accounts['credit']->id, 'description' => $this->creditLineDescription($transaction), 'debit' => '0.00', 'credit' => $amount, 'owner_transaction_id' => $transaction->id],
            ], $userId);
            $entry = $this->journals->post($entry, $userId);
            $transaction->update([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'payment_account_id' => $accounts['payment']?->id,
                'destination_account_id' => $accounts['destination']->id,
                'debit_account_id' => $accounts['debit']->id,
                'credit_account_id' => $accounts['credit']->id,
                'posted_by' => $userId,
                'posted_at' => now(),
                'updated_by' => $userId,
            ]);
            $this->audit->record('owner_compensation.posted', $transaction, $userId, ['status' => 'draft'], ['status' => 'posted', 'journal_entry_id' => $entry->id], $ip);

            return $entry;
        }, 3);
    }

    public function reverse(OwnerTransaction $transaction, ?int $userId, ?string $reason = null, ?string $ip = null): OwnerTransaction
    {
        return DB::transaction(function () use ($transaction, $userId, $reason, $ip): OwnerTransaction {
            $transaction = OwnerTransaction::query()->with('journalEntry')->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status !== 'posted' || ! $transaction->journalEntry) {
                throw ValidationException::withMessages(['transaction' => 'Only posted owner compensation records can be reversed.']);
            }

            $reversalJournal = $this->journals->reverse($transaction->journalEntry, $userId, $reason ?: 'Owner compensation reversal');
            $reversal = OwnerTransaction::query()->create([
                'transaction_number' => $transaction->transaction_number.'-R',
                'transaction_date' => now()->toDateString(),
                'transaction_type' => $transaction->transaction_type,
                'amount' => $transaction->amount,
                'payment_account_id' => $transaction->payment_account_id,
                'destination_account_id' => $transaction->destination_account_id,
                'debit_account_id' => $transaction->credit_account_id,
                'credit_account_id' => $transaction->debit_account_id,
                'description' => 'Reversal of '.$transaction->transaction_number.($reason ? ': '.trim($reason) : ''),
                'payment_method' => $transaction->payment_method,
                'reference_number' => 'REV-'.$transaction->transaction_number,
                'notes' => $reason,
                'status' => 'reversed',
                'journal_entry_id' => $reversalJournal->id,
                'posted_by' => $userId,
                'posted_at' => now(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $transaction->update(['status' => 'reversed', 'reversed_at' => now(), 'reversal_transaction_id' => $reversal->id, 'updated_by' => $userId]);
            $this->audit->record('owner_compensation.reversed', $transaction, $userId, ['status' => 'posted'], ['status' => 'reversed', 'reversal_transaction_id' => $reversal->id, 'reversal_journal_id' => $reversalJournal->id], $ip);

            return $transaction->fresh(['journalEntry', 'reversalTransaction.journalEntry']);
        }, 3);
    }

    public function cancel(OwnerTransaction $transaction, ?int $userId, ?string $ip = null): OwnerTransaction
    {
        return DB::transaction(function () use ($transaction, $userId, $ip): OwnerTransaction {
            $transaction = OwnerTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if (! $transaction->mayBePosted()) {
                throw ValidationException::withMessages(['transaction' => 'Only draft owner compensation records can be cancelled.']);
            }
            $transaction->update(['status' => 'cancelled', 'updated_by' => $userId]);
            $this->audit->record('owner_compensation.cancelled', $transaction, $userId, ['status' => 'draft'], ['status' => 'cancelled'], $ip);

            return $transaction;
        }, 3);
    }

    /** @param array{debit:\App\Models\AccountingAccount,credit:\App\Models\AccountingAccount,destination:\App\Models\AccountingAccount,payment:?\App\Models\AccountingAccount} $accounts */
    private function assertBalanceProtection(OwnerTransaction $transaction, array $accounts): void
    {
        $amount = $this->cents($transaction->amount);
        if ($transaction->canonicalType() === 'drawing') {
            $balance = $this->ownerCompensation->balanceAsOf($accounts['credit'], $transaction->transaction_date);
            if ($balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'Owner drawings cannot exceed the available business cash or bank balance.']);
            }
        }
        if (in_array($transaction->canonicalType(), ['business_reserve', 'emergency_reserve'], true)) {
            $balance = $this->ownerCompensation->balanceAsOf($accounts['debit'], $transaction->transaction_date);
            if ($balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'Reserve allocations cannot exceed the available Retained Earnings balance.']);
            }
        }
    }

    private function journalDescription(OwnerTransaction $transaction): string
    {
        return $transaction->typeLabel().' – '.($transaction->description ?: $transaction->transaction_date->format('F Y'));
    }

    private function creditLineDescription(OwnerTransaction $transaction): string
    {
        return match ($transaction->canonicalType()) {
            'salary' => 'Owner salary payment',
            'drawing' => 'Owner drawing payment',
            'capital_contribution' => 'Owner capital contribution',
            default => $transaction->typeLabel(),
        };
    }

    private function cents(mixed $amount): int
    {
        preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim((string) $amount), $matches);
        return ((int) ($matches[1] ?? 0) * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function decimal(mixed $amount): string
    {
        $cents = $this->cents($amount);

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
