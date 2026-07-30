<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlowFilterRequest;
use App\Http\Requests\CashFlowMappingRequest;
use App\Models\AccountingAccount;
use App\Models\CashFlowAccountMapping;
use App\Services\AccountingAuditService;
use App\Services\AccountingExportService;
use App\Services\CashFlowConfigurationService;
use App\Services\CashFlowExportService;
use App\Services\CashFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowController extends Controller
{
    public function index(CashFlowFilterRequest $request, CashFlowService $cashFlow): View
    {
        return view('admin.accounting.cash-flow.index', $this->viewData($request, $cashFlow));
    }

    public function statement(CashFlowFilterRequest $request, CashFlowService $cashFlow): View
    {
        return view('admin.accounting.cash-flow.statement', $this->viewData($request, $cashFlow));
    }

    public function movements(CashFlowFilterRequest $request, CashFlowService $cashFlow): View
    {
        return view('admin.accounting.cash-flow.movements', $this->viewData($request, $cashFlow));
    }

    public function account(AccountingAccount $account, CashFlowFilterRequest $request, CashFlowService $cashFlow): View
    {
        abort_unless($account->cash_flow_enabled, 404);
        $filters = $request->filters();
        $filters['cash_account_id'] = $account->id;
        return view('admin.accounting.cash-flow.account', $this->viewData($request, $cashFlow, $filters) + ['account' => $account]);
    }

    public function reconciliation(CashFlowFilterRequest $request, CashFlowService $cashFlow, AccountingAuditService $audit): View
    {
        $filters = $request->filters();
        $audit->record('cash_flow.reconciliation.viewed', (object) [], $request->user()->id, [], ['filters' => $this->auditFilters($filters)], $request->ip());
        return view('admin.accounting.cash-flow.reconciliation', ['reconciliation' => $cashFlow->reconciliation($filters), 'filters' => $filters]);
    }

    public function diagnostics(CashFlowFilterRequest $request, CashFlowService $cashFlow, AccountingAuditService $audit): View
    {
        $filters = $request->filters();
        $audit->record('cash_flow.diagnostics.viewed', (object) [], $request->user()->id, [], ['filters' => $this->auditFilters($filters)], $request->ip());
        return view('admin.accounting.cash-flow.diagnostics', ['issues' => $cashFlow->diagnostics($filters), 'filters' => $filters]);
    }

    public function configuration(CashFlowConfigurationService $configuration): View
    {
        $configuration->ensureDefaults();
        return view('admin.accounting.cash-flow.configuration', [
            'accounts' => AccountingAccount::query()->active()->where('is_cash_account', false)->where('is_cash_equivalent', false)->orderBy('code')->get(),
            'mappings' => CashFlowAccountMapping::query()->with('account')->orderBy('display_order')->get()->keyBy('accounting_account_id'),
            'categoryLabels' => $configuration->categoryLabels(),
        ]);
    }

    public function updateConfiguration(CashFlowMappingRequest $request, CashFlowConfigurationService $configuration, AccountingAuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            foreach ($request->validated('mappings') as $data) {
                $mapping = CashFlowAccountMapping::query()->firstOrNew(['accounting_account_id' => $data['accounting_account_id']]);
                $old = $mapping->exists ? $mapping->only(['classification', 'category_key', 'label', 'display_order', 'is_active']) : [];
                $mapping->fill($data + ['is_active' => (bool) ($data['is_active'] ?? false), 'updated_by' => $request->user()->id]);
                if (! $mapping->exists) $mapping->created_by = $request->user()->id;
                $mapping->save();
                $audit->record('cash_flow.mapping.updated', $mapping, $request->user()->id, $old, $mapping->only(['classification', 'category_key', 'label', 'display_order', 'is_active']), $request->ip());
            }
        });
        $configuration->ensureDefaults();
        return back()->with('success', 'Cash Flow account mappings saved.');
    }

    public function exportStatement(string $format, CashFlowFilterRequest $request, CashFlowService $cashFlow, CashFlowExportService $rows, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        $report = $cashFlow->report($request->filters(), 100000);
        $audit->record('cash_flow.statement.exported', (object) [], $request->user()->id, [], ['format' => $format, 'filters' => $this->auditFilters($request->filters())], $request->ip());
        return $this->download($format, 'cash-flow-statement', 'Cash Flow Statement', $report['period'], ['Cash Flow Item', 'Amount (MYR)'], $rows->statementRows($report), $exports);
    }

    public function exportMovements(string $format, CashFlowFilterRequest $request, CashFlowService $cashFlow, CashFlowExportService $rows, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        $report = $cashFlow->report(collect($request->filters())->except('page')->all(), 100000);
        $audit->record('cash_flow.movements.exported', (object) [], $request->user()->id, [], ['format' => $format, 'filters' => $this->auditFilters($request->filters())], $request->ip());
        return $this->download($format, 'cash-flow-movements', 'Cash Flow Movements', $report['period'], ['Date', 'Posting Date', 'Journal', 'Source', 'Reference', 'Cash Account', 'Counter Account', 'Classification', 'Category', 'Cash In', 'Cash Out', 'Net Movement', 'Running Cash', 'Posted By'], $rows->movementRows($report['movements']->items()), $exports);
    }

    public function exportAccount(AccountingAccount $account, string $format, CashFlowFilterRequest $request, CashFlowService $cashFlow, CashFlowExportService $rows, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        abort_unless($account->cash_flow_enabled, 404);
        $filters = collect($request->filters())->except('page')->all(); $filters['cash_account_id'] = $account->id;
        $report = $cashFlow->report($filters, 100000);
        $audit->record('cash_flow.account.exported', $account, $request->user()->id, [], ['format' => $format, 'filters' => $this->auditFilters($filters)], $request->ip());
        return $this->download($format, 'cash-flow-'.$account->code, 'Cash Flow — '.$account->displayLabel(), $report['period'], ['Date', 'Posting Date', 'Journal', 'Source', 'Reference', 'Cash Account', 'Counter Account', 'Classification', 'Category', 'Cash In', 'Cash Out', 'Net Movement', 'Running Cash', 'Posted By'], $rows->movementRows($report['movements']->items()), $exports);
    }

    public function print(CashFlowFilterRequest $request, CashFlowService $cashFlow): View
    {
        return view('admin.accounting.cash-flow.print', ['report' => $cashFlow->report($request->filters(), 100000), 'filters' => $request->filters()]);
    }

    /** @param array<string,mixed>|null $filters @return array<string,mixed> */
    private function viewData(CashFlowFilterRequest $request, CashFlowService $cashFlow, ?array $filters = null): array
    {
        $filters ??= $request->filters();
        return ['report' => $cashFlow->report($filters), 'filters' => $filters, 'categories' => app(CashFlowConfigurationService::class)->categoryLabels()];
    }

    /** @param array<string,mixed> $filters */
    private function auditFilters(array $filters): array
    {
        return collect($filters)->except('page')->filter(fn ($value) => filled($value))->all();
    }

    /** @param iterable<int,array<int,string>> $data */
    private function download(string $format, string $slug, string $title, array $period, array $headings, iterable $data, AccountingExportService $exports): Response|StreamedResponse|RedirectResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        if ($format === 'xlsx' && ! class_exists(\ZipArchive::class)) return back()->withErrors(['export' => 'Excel export requires the PHP Zip extension. CSV and PDF exports remain available.']);
        return $exports->download($format, $slug, $title, $period, $headings, $data);
    }
}
