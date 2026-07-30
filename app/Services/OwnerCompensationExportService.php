<?php

namespace App\Services;

use App\Models\OwnerTransaction;
use Illuminate\Support\Collection;

class OwnerCompensationExportService
{
    /** @param Collection<int, OwnerTransaction> $transactions @return array<string, int> */
    public function summary(Collection $transactions): array
    {
        $summary = array_fill_keys(array_keys(OwnerTransaction::TYPES), 0);
        foreach ($transactions->where('status', 'posted') as $transaction) {
            $type = $transaction->canonicalType();
            if (array_key_exists($type, $summary)) {
                $summary[$type] += $this->cents($transaction->amount);
            }
        }

        return $summary;
    }

    /** @param Collection<int, OwnerTransaction> $transactions @return Collection<int, array<int, string>> */
    public function rows(Collection $transactions): Collection
    {
        return $transactions->map(fn (OwnerTransaction $transaction): array => [
            $transaction->transaction_number,
            $transaction->transaction_date?->toDateString() ?: '',
            $transaction->typeLabel(),
            $transaction->description,
            $this->currency($this->cents($transaction->amount)),
            $transaction->paymentAccount?->displayLabel() ?: '—',
            ucfirst($transaction->status),
            $transaction->journalEntry?->entry_number ?: '—',
            $transaction->reference_number ?: '—',
            $transaction->creator?->name ?: '—',
            $transaction->poster?->name ?: '—',
        ]);
    }

    public function currency(int $cents): string
    {
        return 'RM '.intdiv($cents, 100).'.'.str_pad((string) abs($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function cents(mixed $amount): int
    {
        preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim((string) $amount), $matches);

        return ((int) ($matches[1] ?? 0) * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
