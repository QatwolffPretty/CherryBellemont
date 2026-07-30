<x-layouts.admin title="Chart of Accounts | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" title="Chart of Accounts" subtitle="Manage the accounts used by future journals, ledgers, and financial reports.">
            <x-slot:actions>
                <x-admin.button :href="route('admin.accounting.accounts.create')" icon="bi-plus-lg">Create Account</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stats-card label="Total Accounts" :value="$summary['total']" icon="bi-list-columns" />
            <x-admin.stats-card label="Active Accounts" :value="$summary['active']" icon="bi-check2-circle" />
            <x-admin.stats-card label="System Accounts" :value="$summary['system']" icon="bi-shield-lock" />
            <x-admin.stats-card label="Assets" :value="$summary['types']->get('asset', 0)" icon="bi-wallet2" />
            <x-admin.stats-card label="Liabilities" :value="$summary['types']->get('liability', 0)" icon="bi-arrow-down-circle" />
            <x-admin.stats-card label="Equity" :value="$summary['types']->get('equity', 0)" icon="bi-pie-chart" />
            <x-admin.stats-card label="Revenue" :value="$summary['types']->get('revenue', 0)" icon="bi-graph-up-arrow" />
            <x-admin.stats-card label="Expenses" :value="$summary['types']->get('expense', 0) + $summary['types']->get('cost_of_goods_sold', 0)" icon="bi-arrow-up-circle" />
        </div>

        <x-admin.card class="mt-8" title="Find an account">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="GET" action="{{ route('admin.accounting.accounts.index') }}">
                <x-admin.form-input name="search" label="Search" :value="$filters['search'] ?? null" placeholder="Code or account name" />
                <x-admin.select name="type" label="Account type">
                    <option value="">All account types</option>
                    @foreach($types as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </x-admin.select>
                <x-admin.select name="subtype" label="Account subtype">
                    <option value="">All subtypes</option>
                    @foreach($subtypes as $subtype)<option value="{{ $subtype }}" @selected(($filters['subtype'] ?? '') === $subtype)>{{ $subtype }}</option>@endforeach
                </x-admin.select>
                <x-admin.select name="status" label="Status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></x-admin.select>
                <x-admin.select name="kind" label="Account ownership"><option value="">System and custom</option><option value="system" @selected(($filters['kind'] ?? '') === 'system')>System</option><option value="custom" @selected(($filters['kind'] ?? '') === 'custom')>Custom</option></x-admin.select>
                <x-admin.select name="hierarchy" label="Hierarchy"><option value="">Parents and children</option><option value="parent" @selected(($filters['hierarchy'] ?? '') === 'parent')>Parent accounts</option><option value="child" @selected(($filters['hierarchy'] ?? '') === 'child')>Child accounts</option></x-admin.select>
                <x-admin.select name="normal_balance" label="Normal balance"><option value="">Debit and credit</option><option value="debit" @selected(($filters['normal_balance'] ?? '') === 'debit')>Debit</option><option value="credit" @selected(($filters['normal_balance'] ?? '') === 'credit')>Credit</option></x-admin.select>
                <div class="flex items-end gap-3"><x-admin.button type="submit" icon="bi-funnel">Filter</x-admin.button><x-admin.button variant="outline" :href="route('admin.accounting.accounts.index')">Clear</x-admin.button></div>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-8" title="Accounts">
            @error('account')<p class="mb-5 border border-red-300/40 bg-red-950/30 px-4 py-3 text-sm text-red-100">{{ $message }}</p>@enderror
            <x-admin.table>
                <x-slot:head><tr><th>Code</th><th>Account Name</th><th>Type</th><th>Subtype</th><th>Normal Balance</th><th>Parent Account</th><th>Opening Balance</th><th>Status</th><th>System / Custom</th><th class="text-right">Actions</th></tr></x-slot:head>
                @forelse($accounts as $account)
                    <tr>
                        <td class="font-medium text-gold">{{ $account->code }}</td>
                        <td><a class="text-cream hover:text-gold" href="{{ route('admin.accounting.accounts.show', $account) }}">@if($account->parent_id)<span class="mr-2 text-gold/60">↳</span>@endif{{ $account->name }}</a><p class="mt-1 text-xs text-cream/50">{{ $account->children_count }} child {{ Str::plural('account', $account->children_count) }} · {{ $account->lines_count }} journal {{ Str::plural('line', $account->lines_count) }}</p></td>
                        <td>{{ $types[$account->type] ?? str($account->type)->replace('_', ' ')->title() }}</td>
                        <td>{{ $account->subtype ?: '—' }}</td>
                        <td>{{ str($account->normal_balance)->title() }}</td>
                        <td>{{ $account->parent?->displayLabel() ?? '—' }}</td>
                        <td>RM {{ number_format((float) $account->opening_balance, 2) }} @if($account->opening_balance_date)<p class="mt-1 text-xs text-cream/50">{{ $account->opening_balance_date->format('d M Y') }}</p>@endif</td>
                        <td><x-admin.badge :status="$account->is_active ? 'active' : 'inactive'" /></td>
                        <td><x-admin.badge :status="$account->is_system ? 'processing' : 'pending'" :label="$account->is_system ? 'System' : 'Custom'" /></td>
                        <td><div class="flex justify-end gap-3 whitespace-nowrap"><a class="text-gold hover:text-cream" href="{{ route('admin.accounting.accounts.show', $account) }}">View</a><a class="text-gold hover:text-cream" href="{{ route('admin.accounting.accounts.edit', $account) }}">Edit</a><form method="POST" action="{{ route('admin.accounting.accounts.toggle-status', $account) }}">@csrf @method('PATCH')<button class="text-gold hover:text-cream" type="submit">{{ $account->is_active ? 'Deactivate' : 'Activate' }}</button></form>@if(! $account->is_system)<form method="POST" action="{{ route('admin.accounting.accounts.destroy', $account) }}" onsubmit="return confirm('Delete this unused custom account? This cannot be undone.');">@csrf @method('DELETE')<button class="text-red-200 hover:text-red-100" type="submit">Delete</button></form>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-10 text-center text-cream/60">No accounts match these filters. <a class="text-gold" href="{{ route('admin.accounting.accounts.create') }}">Create an account</a>.</td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-5">{{ $accounts->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
