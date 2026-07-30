<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountingAccountRequest;
use App\Http\Requests\UpdateAccountingAccountRequest;
use App\Http\Requests\AccountingAccountRequest;
use App\Models\AccountingAccount;
use App\Services\AccountingAccountService;
use App\Services\AccountingAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountingAccountController extends Controller
{
    public function index(Request $request, AccountingAccountService $accounts): View
    {
        $accounts->ensureDefaults();

        $sort = in_array($request->string('sort')->value(), ['code', 'name', 'type', 'subtype', 'normal_balance', 'opening_balance', 'is_active'], true)
            ? $request->string('sort')->value()
            : 'code';
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query = AccountingAccount::query()
            ->with('parent:id,code,name')
            ->withCount(['lines', 'children'])
            ->searchable($request->string('search')->value())
            ->when($request->filled('type'), fn ($builder) => $builder->where('type', $request->string('type')->value()))
            ->when($request->filled('subtype'), fn ($builder) => $builder->where('subtype', $request->string('subtype')->value()))
            ->when($request->string('status')->value() === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($request->string('status')->value() === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->when($request->string('kind')->value() === 'system', fn ($builder) => $builder->where('is_system', true))
            ->when($request->string('kind')->value() === 'custom', fn ($builder) => $builder->where('is_system', false))
            ->when($request->string('hierarchy')->value() === 'parent', fn ($builder) => $builder->whereNull('parent_id'))
            ->when($request->string('hierarchy')->value() === 'child', fn ($builder) => $builder->whereNotNull('parent_id'))
            ->when($request->filled('normal_balance'), fn ($builder) => $builder->where('normal_balance', $request->string('normal_balance')->value()));

        return view('admin.accounting.accounts.index', [
            'accounts' => $query->orderBy($sort, $direction)->orderBy('code')->paginate(30)->withQueryString(),
            'filters' => $request->only(['search', 'type', 'subtype', 'status', 'kind', 'hierarchy', 'normal_balance', 'sort', 'direction']),
            'types' => $accounts->accountTypes(),
            'subtypes' => $accounts->subtypesFor($request->string('type')->value()),
            'summary' => [
                'total' => AccountingAccount::query()->count(),
                'active' => AccountingAccount::query()->active()->count(),
                'system' => AccountingAccount::query()->system()->count(),
                'types' => AccountingAccount::query()->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type'),
            ],
        ]);
    }

    public function create(AccountingAccountService $accounts): View
    {
        $accounts->ensureDefaults();

        return view('admin.accounting.accounts.form', $this->formData(null, $accounts));
    }

    public function store(StoreAccountingAccountRequest $request, AccountingAuditService $audit): RedirectResponse
    {
        $account = DB::transaction(function () use ($request, $audit): AccountingAccount {
            $data = $this->normaliseData($request);
            $account = AccountingAccount::query()->create($data + [
                'is_system' => false,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $audit->record('account.created', $account, $request->user()->id, [], $this->auditValues($account), $request->ip());

            return $account;
        });

        return to_route('admin.accounting.accounts.show', $account)->with('success', 'Chart of Accounts entry created.');
    }

    public function show(AccountingAccount $account): View
    {
        return view('admin.accounting.accounts.show', [
            'account' => $account->load(['parent', 'children' => fn ($query) => $query->orderBy('code'), 'creator', 'updater'])->loadCount('lines'),
        ]);
    }

    public function edit(AccountingAccount $account, AccountingAccountService $accounts): View
    {
        return view('admin.accounting.accounts.form', $this->formData($account, $accounts));
    }

    public function update(UpdateAccountingAccountRequest $request, AccountingAccount $account, AccountingAccountService $accounts, AccountingAuditService $audit): RedirectResponse
    {
        $previouslyActive = $account->is_active;

        DB::transaction(function () use ($request, $account, $accounts, $audit, $previouslyActive): void {
            $data = $this->normaliseData($request);

            if ($previouslyActive && ! $data['is_active']) {
                $accounts->assertCanDeactivate($account);
            }

            $old = $this->auditValues($account);
            $account->update($data + ['updated_by' => $request->user()->id]);
            $audit->record(
                $previouslyActive !== $account->is_active ? ($account->is_active ? 'account.activated' : 'account.deactivated') : 'account.updated',
                $account,
                $request->user()->id,
                $old,
                $this->auditValues($account),
                $request->ip(),
            );
        });

        return to_route('admin.accounting.accounts.show', $account)->with('success', 'Chart of Accounts entry updated.');
    }

    public function toggleStatus(Request $request, AccountingAccount $account, AccountingAccountService $accounts, AccountingAuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $account, $accounts, $audit): void {
            if ($account->is_active) {
                $accounts->assertCanDeactivate($account);
            }

            $old = ['is_active' => $account->is_active];
            $account->update(['is_active' => ! $account->is_active, 'updated_by' => $request->user()->id]);
            $audit->record($account->is_active ? 'account.activated' : 'account.deactivated', $account, $request->user()->id, $old, ['is_active' => $account->is_active], $request->ip());
        });

        return back()->with('success', 'Account status updated.');
    }

    public function destroy(Request $request, AccountingAccount $account, AccountingAccountService $accounts, AccountingAuditService $audit): RedirectResponse
    {
        try {
            $accounts->assertCanDelete($account);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        DB::transaction(function () use ($request, $account, $audit): void {
            $values = $this->auditValues($account);
            $audit->record('account.deleted', $account, $request->user()->id, $values, [], $request->ip());
            $account->delete();
        });

        return to_route('admin.accounting.accounts.index')->with('success', 'Unused custom account deleted.');
    }

    /** @return array<string, mixed> */
    private function formData(?AccountingAccount $account, AccountingAccountService $accounts): array
    {
        return [
            'account' => $account,
            'types' => $accounts->accountTypes(),
            'subtypesByType' => \App\Support\AccountingCatalog::subtypes(),
            'parents' => $accounts->eligibleParents($account),
        ];
    }

    /** @return array<string, mixed> */
    private function normaliseData(AccountingAccountRequest $request): array
    {
        return $request->validated() + [
            'is_active' => $request->boolean('is_active'),
            'allow_manual_posting' => $request->has('allow_manual_posting') ? $request->boolean('allow_manual_posting') : true,
            'is_cash_account' => $request->boolean('is_cash_account'),
            'is_cash_equivalent' => $request->boolean('is_cash_equivalent'),
            'is_clearing_account' => $request->boolean('is_clearing_account'),
            'cash_flow_enabled' => $request->boolean('cash_flow_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(AccountingAccount $account): array
    {
        return $account->only(['code', 'name', 'type', 'subtype', 'normal_balance', 'parent_id', 'opening_balance', 'opening_balance_date', 'is_active', 'is_system', 'allow_manual_posting', 'is_cash_account', 'is_cash_equivalent', 'is_clearing_account', 'cash_flow_enabled']);
    }
}
