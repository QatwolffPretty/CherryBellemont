<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

/** Source-aware classification that falls back to editable counter-account mappings. */
class CashFlowClassificationService
{
    private ?Collection $mappings = null;

    public function __construct(private readonly CashFlowConfigurationService $configuration) {}

    /** @param Collection<int, JournalEntryLine> $counterLines @return array{classification:string,category:string,label:string} */
    public function classify(JournalEntry $journal, Collection $counterLines, bool $internalTransfer): array
    {
        if ($internalTransfer) {
            return $this->result('internal_transfer', 'internal_transfer');
        }

        $owner = $counterLines->pluck('ownerTransaction')->filter()->first();
        if ($journal->source_type === 'owner_compensation' && $owner) {
            return match ($owner->canonicalType()) {
                'salary' => $this->result('operating', 'salary'),
                'drawing' => $this->result('financing', 'owner_drawings'),
                'capital_contribution' => $this->result('financing', 'owner_capital'),
                'business_reserve', 'emergency_reserve' => $this->result('non_cash', 'reserve_transfer'),
                default => $this->result('unclassified', 'unclassified'),
            };
        }

        $source = $this->effectiveSource($journal);
        if ($source === 'order') return $this->result('operating', 'customer_receipts');
        if ($source === 'refund') return $this->result('operating', 'refunds');
        if ($source === 'payment_fee') return $this->result('operating', 'payment_fees');
        if ($source === 'manual_income') return $this->result('operating', 'other_operating_receipts');

        $mapping = ($this->mappings ??= $this->configuration->mappings())->keyBy('accounting_account_id');
        foreach ($counterLines as $line) {
            if ($configured = $mapping->get($line->account_id)) {
                return $this->result($configured->classification, $configured->category_key ?: 'unclassified', $configured->label ?: null);
            }
        }

        foreach ($counterLines as $line) {
            if ($fallback = $this->fromAccount($line->account)) return $fallback;
        }

        return $this->result('unclassified', 'unclassified');
    }

    /** @return array{classification:string,category:string,label:string}|null */
    private function fromAccount(?AccountingAccount $account): ?array
    {
        if (! $account) return null;
        if ($account->type === 'revenue') return $this->result('operating', $account->code === '4030' ? 'other_operating_receipts' : 'customer_receipts');
        if ($account->type === 'expense') return $this->result('operating', 'other_operating_payments');
        if ($account->type === 'equity' && in_array($account->code, ['3000', '3100'], true)) return $this->result('financing', $account->code === '3000' ? 'owner_capital' : 'owner_drawings');
        if ($account->type === 'asset' && str_contains(strtolower((string) $account->subtype), 'fixed')) return $this->result('investing', 'asset_purchase');
        if ($account->code === '2000') return $this->result('operating', 'supplier_payments');
        if ($account->code === '2200') return $this->result('operating', 'refunds');

        return null;
    }

    private function effectiveSource(JournalEntry $journal): ?string
    {
        if ($journal->source_type !== 'journal_reversal' || ! $journal->source_id) return $journal->source_type;

        return JournalEntry::query()->whereKey($journal->source_id)->value('source_type') ?: 'journal_reversal';
    }

    /** @return array{classification:string,category:string,label:string} */
    private function result(string $classification, string $category, ?string $label = null): array
    {
        return [
            'classification' => $classification,
            'category' => $category,
            'label' => $label ?: ($this->configuration->categoryLabels()[$category] ?? 'Unclassified Cash Movement'),
        ];
    }
}
