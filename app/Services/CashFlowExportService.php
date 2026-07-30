<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CashFlowExportService
{
    /** @param array<string, mixed> $report @return Collection<int, array<int, string>> */
    public function statementRows(array $report): Collection
    {
        $rows = collect([
            ['Opening Cash Balance', $this->amount($report['opening_cash'])],
        ]);
        foreach (['operating', 'investing', 'financing'] as $classification) {
            $section = $report['sections'][$classification];
            $rows->push([$section['label'], '']);
            foreach ($section['rows'] as $row) $rows->push(['  '.$row['label'], $this->amount($row['net'])]);
            $rows->push(['Net Cash from '.str_replace(' Activities', '', $section['label']), $this->amount($section['net'])]);
        }
        return $rows->merge([
            ['Net Increase / Decrease in Cash', $this->amount($report['net_cash_movement'])],
            ['Closing Cash Balance', $this->amount($report['closing_cash'])],
            ['General Ledger Closing Cash', $this->amount($report['general_ledger_closing_cash'])],
            ['Reconciliation Difference', $this->amount($report['reconciliation_difference'])],
        ]);
    }

    /** @param iterable<int, array<string, mixed>> $movements @return Collection<int, array<int, string>> */
    public function movementRows(iterable $movements): Collection
    {
        return collect($movements)->map(fn (array $row): array => [
            $row['transaction_date'], $row['posting_date'] ?: '—', $row['journal_number'], $row['source_label'], $row['reference'] ?: '—',
            $row['cash_account']->displayLabel(), $row['counter_account'] ?: '—', ucfirst(str_replace('_', ' ', $row['classification'])), $row['category_label'],
            $this->amount($row['cash_in']), $this->amount($row['cash_out']), $this->amount($row['net_movement']), $this->amount($row['running_balance'] ?? 0), $row['posted_by'] ?: '—',
        ]);
    }

    /** @param iterable<int, array<string, mixed>> $positions @return Collection<int, array<int, string>> */
    public function accountRows(iterable $positions): Collection
    {
        return collect($positions)->map(fn (array $row): array => [
            $row['account']->code, $row['account']->name, $this->amount($row['opening_balance']), $this->amount($row['cash_in']), $this->amount($row['cash_out']),
            $this->amount($row['internal_transfers_in']), $this->amount($row['internal_transfers_out']), $this->amount($row['net_movement']), $this->amount($row['closing_balance']),
        ]);
    }

    public function amount(int $cents): string
    {
        return 'RM '.number_format($cents / 100, 2);
    }
}
