<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralLedgerFilterRequest;
use App\Models\AccountingAccount;
use App\Services\AccountingAuditService;
use App\Services\AccountingExportService;
use App\Services\GeneralLedgerService;
use App\Services\LedgerIntegrityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerController extends Controller
{
    public function index(GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger): View
    {
        $filters = $request->filters();
        $report = $ledger->overview($filters);

        return view('admin.accounting.ledger.index', [
            'report' => $report,
            'filters' => $filters,
            'accountTypes' => GeneralLedgerService::ACCOUNT_TYPES,
            'subtypes' => AccountingAccount::query()->whereNotNull('subtype')->distinct()->orderBy('subtype')->pluck('subtype'),
        ]);
    }

    public function account(AccountingAccount $account, GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger): View
    {
        $filters = $request->filters();

        return view('admin.accounting.ledger.account', [
            'ledger' => $ledger->accountLedger($account->load('parent'), $filters),
            'filters' => $filters,
        ]);
    }

    public function trialBalance(GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger): View
    {
        $filters = $request->filters();

        return view('admin.accounting.ledger.trial-balance', [
            'trialBalance' => $ledger->trialBalance($filters),
            'filters' => $filters,
        ]);
    }

    public function integrity(GeneralLedgerFilterRequest $request, LedgerIntegrityService $integrity, AccountingAuditService $audit): View
    {
        $filters = $request->filters();
        $audit->record('ledger.integrity.viewed', (object) [], $request->user()->id, [], ['filters' => $this->auditFilters($filters)], $request->ip());

        return view('admin.accounting.ledger.integrity', [
            'checks' => $integrity->checks(),
            'summary' => $integrity->summary(),
        ]);
    }

    public function exportSummary(string $format, GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        $filters = $request->filters();
        $report = $ledger->overview($filters);
        $audit->record('ledger.summary.exported', (object) [], $request->user()->id, [], ['format' => $format, 'filters' => $this->auditFilters($filters)], $request->ip());

        return $this->download($format, 'general-ledger-summary', 'General Ledger Summary', $report['period'], [
            'Account Code', 'Account Name', 'Account Type', 'Normal Balance', 'Opening Balance', 'Total Debit', 'Total Credit', 'Net Movement', 'Closing Balance', 'Last Activity', 'Status',
        ], $report['rows']->map(fn (array $row): array => [
            $row['account']->code,
            $row['account']->name,
            ucfirst(str_replace('_', ' ', $row['account']->type)),
            ucfirst($row['account']->normal_balance),
            $row['opening_label'],
            $this->amount($row['total_debit']),
            $this->amount($row['total_credit']),
            $row['movement_label'],
            $row['closing_label'],
            $row['last_activity'] ?: '—',
            $row['account']->is_active ? 'Active' : 'Inactive',
        ]), $exports);
    }

    public function exportAccount(AccountingAccount $account, string $format, GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        $filters = collect($request->filters())->except('page')->all();
        $report = $ledger->accountLedger($account->load('parent'), $filters, 100000);
        $audit->record('ledger.account.exported', $account, $request->user()->id, [], ['format' => $format, 'filters' => $this->auditFilters($filters)], $request->ip());

        return $this->download($format, 'general-ledger-'.$account->code, 'General Ledger — '.$account->displayLabel(), $report['period'], [
            'Date', 'Posting Date', 'Journal Number', 'Reference', 'Source', 'Description', 'Line Description', 'Debit', 'Credit', 'Running Balance', 'Status', 'Posted By',
        ], collect($report['rows']->items())->map(fn (array $row): array => [
            $row['transaction_date'], $row['posting_date'] ?: '—', $row['journal_number'], $row['reference'] ?: '—', $row['source']['label'], $row['description'], $row['line_description'] ?: '—', $this->amount($row['debit']), $this->amount($row['credit']), $row['running_balance_label'], ucfirst($row['status']), $row['posted_by'] ?: '—',
        ]), $exports);
    }

    public function exportTrialBalance(string $format, GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        $filters = $request->filters();
        $report = $ledger->trialBalance($filters);
        $audit->record('ledger.trial_balance.exported', (object) [], $request->user()->id, [], ['format' => $format, 'filters' => $this->auditFilters($filters)], $request->ip());

        return $this->download($format, 'trial-balance', 'Trial Balance', $report['period'], ['Account Code', 'Account Name', 'Debit Balance', 'Credit Balance'], $report['rows']->map(fn (array $row): array => [
            $row['account']->code, $row['account']->name, $this->amount($row['debit_balance']), $this->amount($row['credit_balance']),
        ]), $exports);
    }

    public function printSummary(GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger): View
    {
        $report = $ledger->overview($request->filters());

        return view('admin.accounting.ledger.print', ['title' => 'General Ledger Summary', 'period' => $report['period'], 'headings' => ['Code', 'Account', 'Type', 'Opening', 'Debit', 'Credit', 'Movement', 'Closing'], 'rows' => $report['rows']->map(fn (array $row): array => [$row['account']->code, $row['account']->name, ucfirst(str_replace('_', ' ', $row['account']->type)), $row['opening_label'], $this->amount($row['total_debit']), $this->amount($row['total_credit']), $row['movement_label'], $row['closing_label']] )]);
    }

    public function printAccount(AccountingAccount $account, GeneralLedgerFilterRequest $request, GeneralLedgerService $ledger): View
    {
        $report = $ledger->accountLedger($account->load('parent'), collect($request->filters())->except('page')->all(), 100000);

        return view('admin.accounting.ledger.print', ['title' => 'Account Ledger — '.$account->displayLabel(), 'period' => $report['period'], 'headings' => ['Date', 'Journal', 'Source', 'Description', 'Debit', 'Credit', 'Running Balance'], 'rows' => collect($report['rows']->items())->map(fn (array $row): array => [$row['transaction_date'], $row['journal_number'], $row['source']['label'], $row['line_description'] ?: $row['description'], $this->amount($row['debit']), $this->amount($row['credit']), $row['running_balance_label']])]);
    }

    /** @param iterable<int, array<int, mixed>> $rows */
    private function download(string $format, string $slug, string $title, array $period, array $headings, iterable $rows, AccountingExportService $exports): Response|StreamedResponse|RedirectResponse
    {
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            abort(404);
        }
        if ($format === 'xlsx' && ! class_exists(\ZipArchive::class)) {
            return back()->withErrors(['export' => 'Excel export requires the PHP Zip extension. CSV and PDF exports remain available.']);
        }

        return $exports->download($format, $slug, $title, $period, $headings, $rows);
    }

    /** @param array<string, mixed> $filters */
    private function auditFilters(array $filters): array
    {
        return collect($filters)->except('page')->filter(fn ($value) => filled($value))->all();
    }

    private function amount(int $cents): string
    {
        return 'RM '.number_format($cents / 100, 2);
    }
}
