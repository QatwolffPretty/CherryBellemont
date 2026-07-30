<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalEntryFilterRequest;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function index(JournalEntryFilterRequest $request): View
    {
        $filters = $request->filters();
        $entries = JournalEntry::query()
            ->with('poster:id,name')
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '<=', $date))
            ->when($filters['journal_number'] ?? null, fn ($query, $value) => $query->where('entry_number', 'like', '%'.$value.'%'))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['description'] ?? null, fn ($query, $value) => $query->where('description', 'like', '%'.$value.'%'))
            ->when($filters['source'] ?? null, fn ($query, $value) => $query->where('source_type', $value))
            ->when($filters['posted_by'] ?? null, fn ($query, $value) => $query->where('posted_by', $value))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.accounting.journals.index', [
            'entries' => $entries,
            'filters' => $filters,
            'sources' => JournalEntry::query()->whereNotNull('source_type')->distinct()->orderBy('source_type')->pluck('source_type'),
            'posters' => User::query()->whereHas('postedJournalEntries')->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'total' => JournalEntry::query()->count(),
                'draft' => JournalEntry::query()->where('status', 'draft')->count(),
                'posted' => JournalEntry::query()->where('status', 'posted')->count(),
                'reversed' => JournalEntry::query()->where('status', 'reversed')->count(),
            ],
        ]);
    }

    public function create(AccountingAccountService $accounts): View
    {
        $accounts->ensureDefaults();

        return view('admin.accounting.journals.form', [
            'entry' => null,
            'accounts' => AccountingAccount::query()->active()->where('allow_manual_posting', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreJournalEntryRequest $request, JournalPostingService $journals): RedirectResponse
    {
        $entry = $journals->createDraft($request->validated(), $request->validated('lines'), $request->user()->id);

        return to_route('admin.accounting.journals.show', $entry)->with('success', 'Balanced journal draft created.');
    }

    public function show(JournalEntry $journalEntry): View
    {
        return view('admin.accounting.journals.show', [
            'entry' => $journalEntry->load(['lines.account', 'poster', 'reverser', 'reversalEntry', 'creator', 'updater']),
        ]);
    }

    public function edit(JournalEntry $journalEntry): View
    {
        abort_unless($journalEntry->status === 'draft', 409, 'Only draft journal entries can be edited.');

        return view('admin.accounting.journals.form', [
            'entry' => $journalEntry->load('lines'),
            'accounts' => AccountingAccount::query()->active()->where('allow_manual_posting', true)->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalPostingService $journals): RedirectResponse
    {
        $entry = $journals->updateDraft($journalEntry, $request->validated(), $request->validated('lines'), $request->user()->id);

        return to_route('admin.accounting.journals.show', $entry)->with('success', 'Journal draft updated.');
    }

    public function post(Request $request, JournalEntry $journalEntry, JournalPostingService $journals): RedirectResponse
    {
        try {
            $journals->post($journalEntry, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Journal posted. Posted journals are immutable and must be corrected by reversal.');
    }

    public function reverse(Request $request, JournalEntry $journalEntry, JournalPostingService $journals): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $reversal = $journals->reverse($journalEntry, $request->user()->id, $data['reason'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return to_route('admin.accounting.journals.show', $reversal)->with('success', 'Balanced reversal journal created.');
    }

    public function cancel(Request $request, JournalEntry $journalEntry, JournalPostingService $journals): RedirectResponse
    {
        try {
            $journals->cancel($journalEntry, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return to_route('admin.accounting.journals.show', $journalEntry)->with('success', 'Draft journal cancelled.');
    }
}
