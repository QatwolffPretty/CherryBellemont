<x-layouts.admin title="General Ledger | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" title="General Ledger" subtitle="Account activity is generated from posted journals only. Parent rows show direct postings only, so child balances are never double counted.">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.trial-balance', $filters)" icon="bi-table">Trial Balance</x-admin.button>
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.integrity', $filters)" icon="bi-shield-check">Integrity Checks</x-admin.button>
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.print', $filters)" icon="bi-printer" target="_blank">Print</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mt-8" title="Report filters">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" method="GET" action="{{ route('admin.accounting.ledger.index') }}">
                <x-admin.select name="range" label="Date shortcut">
                    @foreach(['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'this_quarter' => 'This Quarter', 'this_year' => 'This Year', 'last_year' => 'Last Year', 'custom' => 'Custom range'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['range'] ?? 'this_year') === $value)>{{ $label }}</option>
                    @endforeach
                </x-admin.select>
                <x-admin.form-input name="from_date" type="date" label="Start date" :value="$filters['from_date'] ?? null" />
                <x-admin.form-input name="to_date" type="date" label="End date" :value="$filters['to_date'] ?? null" />
                <x-admin.form-input name="search" label="Search" :value="$filters['search'] ?? null" placeholder="Account code or name" />
                <x-admin.select name="account_id" label="Account"><option value="">All accounts</option>@foreach($report['accounts'] as $account)<option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->displayLabel() }}</option>@endforeach</x-admin.select>
                <x-admin.form-input name="account_code" label="Account code" :value="$filters['account_code'] ?? null" placeholder="e.g. 4000" />
                <x-admin.select name="account_type" label="Account type"><option value="">All account types</option>@foreach($accountTypes as $value => $label)<option value="{{ $value }}" @selected(($filters['account_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-admin.select>
                <x-admin.select name="account_subtype" label="Account subtype"><option value="">All subtypes</option>@foreach($subtypes as $subtype)<option value="{{ $subtype }}" @selected(($filters['account_subtype'] ?? '') === $subtype)>{{ $subtype }}</option>@endforeach</x-admin.select>
                <x-admin.select name="status" label="Account status"><option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Active and inactive</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></x-admin.select>
                <x-admin.select name="kind" label="Account ownership"><option value="all" @selected(($filters['kind'] ?? 'all') === 'all')>System and custom</option><option value="system" @selected(($filters['kind'] ?? '') === 'system')>System</option><option value="custom" @selected(($filters['kind'] ?? '') === 'custom')>Custom</option></x-admin.select>
                <x-admin.select name="normal_balance" label="Normal balance"><option value="">Debit and credit</option><option value="debit" @selected(($filters['normal_balance'] ?? '') === 'debit')>Debit</option><option value="credit" @selected(($filters['normal_balance'] ?? '') === 'credit')>Credit</option></x-admin.select>
                <x-admin.select name="activity" label="Activity"><option value="all" @selected(($filters['activity'] ?? 'all') === 'all')>All accounts</option><option value="with" @selected(($filters['activity'] ?? '') === 'with')>Has activity</option><option value="without" @selected(($filters['activity'] ?? '') === 'without')>No activity</option></x-admin.select>
                <x-admin.form-input name="min_closing" label="Minimum closing balance" :value="$filters['min_closing'] ?? null" inputmode="decimal" />
                <x-admin.form-input name="max_closing" label="Maximum closing balance" :value="$filters['max_closing'] ?? null" inputmode="decimal" />
                <div class="flex items-end gap-3"><x-admin.button type="submit" icon="bi-funnel">Apply Filters</x-admin.button><x-admin.button variant="outline" :href="route('admin.accounting.ledger.index')">Clear</x-admin.button></div>
            </form>
            @if($errors->any())<div class="mt-5 border border-red-300/40 bg-red-950/30 px-4 py-3 text-sm text-red-100">{{ $errors->first() }}</div>@endif
        </x-admin.card>

        <p class="mt-5 text-sm text-cream/65">Reporting period: <span class="text-gold">{{ $report['period']['label'] }}</span> · {{ $report['period']['start']->format('d M Y') }} to {{ $report['period']['end']->format('d M Y') }}.</p>

        @if($report['metrics']['unbalanced_posted_journals'] > 0)
            <div class="mt-5 border border-red-300/50 bg-red-950/30 px-5 py-4 text-red-100"><i class="bi bi-exclamation-triangle mr-2"></i>{{ $report['metrics']['unbalanced_posted_journals'] }} posted journal {{ Str::plural('requires', $report['metrics']['unbalanced_posted_journals']) }} review. <a class="ml-2 text-gold underline" href="{{ route('admin.accounting.ledger.integrity') }}">Inspect integrity checks</a>.</div>
        @endif

        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stats-card label="Opening Assets" :value="'RM '.number_format($report['metrics']['opening_assets'] / 100, 2)" />
            <x-admin.stats-card label="Opening Liabilities" :value="'RM '.number_format($report['metrics']['opening_liabilities'] / 100, 2)" />
            <x-admin.stats-card label="Opening Equity" :value="'RM '.number_format($report['metrics']['opening_equity'] / 100, 2)" />
            <x-admin.stats-card label="Accounts With Activity" :value="$report['metrics']['accounts_with_activity']" />
            <x-admin.stats-card label="Total Debits" :value="'RM '.number_format($report['metrics']['total_debits'] / 100, 2)" />
            <x-admin.stats-card label="Total Credits" :value="'RM '.number_format($report['metrics']['total_credits'] / 100, 2)" />
            <x-admin.stats-card label="Net Revenue Movement" :value="'RM '.number_format($report['metrics']['net_revenue_movement'] / 100, 2)" />
            <x-admin.stats-card label="Net Expense Movement" :value="'RM '.number_format($report['metrics']['net_expense_movement'] / 100, 2)" />
            <x-admin.stats-card label="Closing Assets" :value="'RM '.number_format($report['metrics']['closing_assets'] / 100, 2)" />
            <x-admin.stats-card label="Closing Liabilities" :value="'RM '.number_format($report['metrics']['closing_liabilities'] / 100, 2)" />
            <x-admin.stats-card label="Closing Equity" :value="'RM '.number_format($report['metrics']['closing_equity'] / 100, 2)" />
            <x-admin.stats-card label="Unbalanced Posted Journals" :value="$report['metrics']['unbalanced_posted_journals']" :accent="$report['metrics']['unbalanced_posted_journals'] > 0" />
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            @foreach(['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.export', array_merge(['format' => $format], collect($filters)->except('page')->all()))" icon="bi-download">Export {{ $label }}</x-admin.button>
            @endforeach
        </div>

        <x-admin.card class="mt-6" title="Account summary">
            <x-admin.table class="mt-5">
                <x-slot:head><tr><th>Account</th><th>Type</th><th>Normal</th><th>Opening</th><th>Debit</th><th>Credit</th><th>Movement</th><th>Closing</th><th>Last Activity</th><th>Status</th><th class="text-right">Actions</th></tr></x-slot:head>
                @forelse($report['rows']->groupBy(fn ($row) => $row['account']->type) as $type => $rows)
                    <tr><td colspan="11" class="bg-wine-deep/60 font-semibold text-gold">{{ $accountTypes[$type] ?? str($type)->replace('_', ' ')->title() }}</td></tr>
                    @foreach($rows as $row)
                        @php($account = $row['account'])
                        <tr>
                            <td><a class="text-cream hover:text-gold" href="{{ route('admin.accounting.ledger.account', array_merge(['account' => $account], collect($filters)->except('account_id', 'page')->all())) }}">@if($account->parent_id)<span class="mr-2 text-gold/60">↳</span>@endif<span class="text-gold">{{ $account->code }}</span> — {{ $account->name }}</a></td>
                            <td>{{ $accountTypes[$account->type] ?? $account->type }}</td><td>{{ strtoupper($account->normal_balance === 'debit' ? 'Dr' : 'Cr') }}</td><td>{{ $row['opening_label'] }}</td><td>RM {{ number_format($row['total_debit'] / 100, 2) }}</td><td>RM {{ number_format($row['total_credit'] / 100, 2) }}</td><td>{{ $row['movement_label'] }}</td><td>{{ $row['closing_label'] }}</td><td>{{ $row['last_activity'] ? \Carbon\Carbon::parse($row['last_activity'])->format('d M Y') : '—' }}</td><td><x-admin.badge :status="$account->is_active ? 'active' : 'inactive'" :label="$account->is_active ? 'Active' : 'Inactive'" /></td>
                            <td><div class="flex justify-end gap-3 whitespace-nowrap"><a class="text-gold hover:text-cream" href="{{ route('admin.accounting.ledger.account', array_merge(['account' => $account], collect($filters)->except('account_id', 'page')->all())) }}">View Ledger</a><a class="text-gold hover:text-cream" href="{{ route('admin.accounting.ledger.account.export', array_merge(['account' => $account, 'format' => 'csv'], collect($filters)->except('account_id', 'page')->all())) }}">Export</a></div></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="11" class="py-10 text-center text-cream/60">No accounts match the selected filters. Existing inactive account history can be viewed by choosing the inactive or all status filter.</td></tr>
                @endforelse
            </x-admin.table>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
