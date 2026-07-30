<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OwnerCompensationFilterRequest;
use App\Http\Requests\OwnerCompensationRequest;
use App\Models\AccountingAccount;
use App\Models\OwnerTransaction;
use App\Services\AccountingAuditService;
use App\Services\AccountingExportService;
use App\Services\OwnerCompensationExportService;
use App\Services\OwnerCompensationPostingService;
use App\Services\OwnerCompensationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerCompensationController extends Controller
{
    public function index(OwnerCompensationFilterRequest $request, OwnerCompensationService $ownerCompensation): View
    {
        $filters = $request->filters();

        return view('admin.accounting.owner-transactions.index', [
            'transactions' => $ownerCompensation->paginate($filters),
            'filters' => $filters,
            'period' => $ownerCompensation->period($filters),
            'overview' => $ownerCompensation->overview($filters),
            'paymentAccounts' => $this->paymentAccounts(),
        ]);
    }

    public function create(OwnerCompensationService $ownerCompensation): View
    {
        return view('admin.accounting.owner-transactions.form', $this->formData(null, $ownerCompensation));
    }

    public function store(OwnerCompensationRequest $request, OwnerCompensationService $ownerCompensation): RedirectResponse
    {
        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('accounting/owner-compensation/'.now()->format('Y'), 'local')
            : null;
        $transaction = $ownerCompensation->createDraft($request->validated(), $request->user()->id, $path, $request->ip());
        if ($path) {
            app(AccountingAuditService::class)->record('owner_compensation.attachment_uploaded', $transaction, $request->user()->id, [], ['attachment' => true], $request->ip());
        }

        return to_route('admin.accounting.owner-transactions.show', $transaction)->with('success', 'Owner compensation draft created. Review the posting preview, then post when ready.');
    }

    public function show(OwnerTransaction $ownerTransaction, OwnerCompensationService $ownerCompensation): View
    {
        return view('admin.accounting.owner-transactions.show', [
            'transaction' => $ownerTransaction->load(['paymentAccount', 'debitAccount', 'creditAccount', 'journalEntry.lines.account', 'creator', 'updater', 'poster', 'reversalTransaction.journalEntry']),
            'postingWarnings' => $ownerCompensation->postingWarnings($ownerTransaction),
        ]);
    }

    public function edit(OwnerTransaction $ownerTransaction, OwnerCompensationService $ownerCompensation): View
    {
        abort_unless($ownerTransaction->mayBePosted(), 409, 'Posted, reversed, and cancelled owner compensation records are immutable.');

        return view('admin.accounting.owner-transactions.form', $this->formData($ownerTransaction, $ownerCompensation));
    }

    public function update(OwnerCompensationRequest $request, OwnerTransaction $ownerTransaction, OwnerCompensationService $ownerCompensation): RedirectResponse
    {
        abort_unless($ownerTransaction->mayBePosted(), 409, 'Posted, reversed, and cancelled owner compensation records are immutable.');
        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('accounting/owner-compensation/'.now()->format('Y'), 'local')
            : null;
        $updated = $ownerCompensation->updateDraft($ownerTransaction, $request->validated(), $request->user()->id, $path, $request->ip());
        if ($path) {
            app(AccountingAuditService::class)->record('owner_compensation.attachment_uploaded', $updated, $request->user()->id, [], ['attachment' => true], $request->ip());
        }

        return to_route('admin.accounting.owner-transactions.show', $updated)->with('success', 'Owner compensation draft updated.');
    }

    public function post(Request $request, OwnerTransaction $ownerTransaction, OwnerCompensationPostingService $posting): RedirectResponse
    {
        try {
            $entry = $posting->post($ownerTransaction, $request->user()->id, $request->ip());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return to_route('admin.accounting.owner-transactions.show', $ownerTransaction)->with('success', 'Balanced journal '.$entry->entry_number.' posted. This record is now immutable and may only be reversed.');
    }

    public function reverse(Request $request, OwnerTransaction $ownerTransaction, OwnerCompensationPostingService $posting): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        try {
            $transaction = $posting->reverse($ownerTransaction, $request->user()->id, $data['reason'] ?? null, $request->ip());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return to_route('admin.accounting.owner-transactions.show', $transaction)->with('success', 'A balanced reversal journal has been posted. The original record remains in the audit trail.');
    }

    public function cancel(Request $request, OwnerTransaction $ownerTransaction, OwnerCompensationPostingService $posting): RedirectResponse
    {
        try {
            $posting->cancel($ownerTransaction, $request->user()->id, $request->ip());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return to_route('admin.accounting.owner-transactions.show', $ownerTransaction)->with('success', 'Draft owner compensation record cancelled. No journal was created.');
    }

    public function attachment(Request $request, OwnerTransaction $ownerTransaction): Response|StreamedResponse
    {
        abort_unless($ownerTransaction->attachment_path && Storage::disk('local')->exists($ownerTransaction->attachment_path), 404);
        app(AccountingAuditService::class)->record('owner_compensation.attachment_downloaded', $ownerTransaction, $request->user()->id, [], ['attachment' => true], $request->ip());

        return Storage::disk('local')->download($ownerTransaction->attachment_path, 'owner-compensation-'.$ownerTransaction->transaction_number.'.'.pathinfo($ownerTransaction->attachment_path, PATHINFO_EXTENSION));
    }

    public function export(string $format, OwnerCompensationFilterRequest $request, OwnerCompensationService $ownerCompensation, OwnerCompensationExportService $ownerExports, AccountingExportService $exports, AccountingAuditService $audit): Response|StreamedResponse|RedirectResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        if ($format === 'xlsx' && ! class_exists(\ZipArchive::class)) {
            return back()->withErrors(['export' => 'Excel export requires the PHP Zip extension. CSV and PDF exports remain available.']);
        }
        $filters = $request->filters();
        $transactions = $ownerCompensation->exportTransactions($filters);
        $audit->record('owner_compensation.exported', (object) [], $request->user()->id, [], ['format' => $format, 'filters' => collect($filters)->filter(fn ($value) => filled($value))->all()], $request->ip());

        return $exports->download($format, 'owner-compensation', 'Owner Compensation', $ownerCompensation->period($filters), [
            'Transaction Number', 'Date', 'Type', 'Description', 'Amount', 'Payment Account', 'Status', 'Journal Number', 'Reference', 'Created By', 'Posted By',
        ], $ownerExports->rows($transactions));
    }

    public function print(OwnerCompensationFilterRequest $request, OwnerCompensationService $ownerCompensation, OwnerCompensationExportService $ownerExports): View
    {
        $filters = $request->filters();
        $transactions = $ownerCompensation->exportTransactions($filters);

        return view('admin.accounting.ledger.print', [
            'title' => 'Owner Compensation',
            'period' => $ownerCompensation->period($filters),
            'headings' => ['Number', 'Date', 'Type', 'Description', 'Amount', 'Payment Account', 'Status', 'Journal'],
            'rows' => $transactions->map(fn (OwnerTransaction $transaction): array => [
                $transaction->transaction_number, $transaction->transaction_date?->toDateString(), $transaction->typeLabel(), $transaction->description,
                $ownerExports->currency((int) str_replace('.', '', $transaction->amount)), $transaction->paymentAccount?->displayLabel() ?: '—', ucfirst($transaction->status), $transaction->journalEntry?->entry_number ?: '—',
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(?OwnerTransaction $transaction, OwnerCompensationService $ownerCompensation): array
    {
        return [
            'transaction' => $transaction,
            'paymentAccounts' => $this->paymentAccounts(),
            'types' => OwnerTransaction::TYPES,
            'preview' => $ownerCompensation->mappingPreview(),
        ];
    }

    private function paymentAccounts()
    {
        return AccountingAccount::query()->active()->where('type', 'asset')->whereIn('subtype', ['Cash', 'Bank'])->orderBy('code')->get();
    }
}
