<x-layouts.admin title="Owner Compensation | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" title="Owner Compensation" subtitle="Salary is an operating expense. Drawings, capital and reserve allocations are equity movements—not sales or operating expenses.">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.accounting.owner-transactions.print', request()->query())" icon="bi-printer">Print</x-admin.button>
                <x-admin.button :href="route('admin.accounting.owner-transactions.create')" icon="bi-plus-lg">New Transaction</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
            <x-admin.stats-card label="Salary This Month" :value="'RM '.number_format($overview['salary_month'] / 100, 2)" icon="bi-wallet2" />
            <x-admin.stats-card label="Salary This Year" :value="'RM '.number_format($overview['salary_year'] / 100, 2)" icon="bi-calendar3" />
            <x-admin.stats-card label="Drawings This Year" :value="'RM '.number_format($overview['drawings_year'] / 100, 2)" icon="bi-person-dash" />
            <x-admin.stats-card label="Capital Added This Year" :value="'RM '.number_format($overview['capital_year'] / 100, 2)" icon="bi-bank" />
            <x-admin.stats-card label="Draft Transactions" :value="$overview['draft']" icon="bi-pencil-square" />
        </div>
        <div class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stats-card label="Business Reserve Balance" :value="'RM '.number_format(abs($overview['business_reserve_balance']) / 100, 2)" icon="bi-safe" />
            <x-admin.stats-card label="Emergency Reserve Balance" :value="'RM '.number_format(abs($overview['emergency_reserve_balance']) / 100, 2)" icon="bi-shield-check" />
            <x-admin.stats-card label="Posted Transactions" :value="$overview['posted']" icon="bi-journal-check" />
            <x-admin.stats-card label="Reversed Transactions" :value="$overview['reversed']" icon="bi-arrow-counterclockwise" />
        </div>

        <x-admin.card class="mt-8" title="Find owner compensation records">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="GET" action="{{ route('admin.accounting.owner-transactions.index') }}">
                <x-admin.select name="range" label="Date range"><option value="this_year">This Year</option><option value="today" @selected(($filters['range'] ?? '') === 'today')>Today</option><option value="this_month" @selected(($filters['range'] ?? '') === 'this_month')>This Month</option><option value="last_month" @selected(($filters['range'] ?? '') === 'last_month')>Last Month</option><option value="this_quarter" @selected(($filters['range'] ?? '') === 'this_quarter')>This Quarter</option><option value="custom" @selected(($filters['range'] ?? '') === 'custom')>Custom</option></x-admin.select>
                <x-admin.form-input name="from_date" type="date" label="From date" :value="$filters['from_date'] ?? null" />
                <x-admin.form-input name="to_date" type="date" label="To date" :value="$filters['to_date'] ?? null" />
                <x-admin.select name="transaction_type" label="Type"><option value="">All types</option>@foreach(\App\Models\OwnerTransaction::TYPES as $type => $label)<option value="{{ $type }}" @selected(($filters['transaction_type'] ?? '') === $type)>{{ $label }}</option>@endforeach</x-admin.select>
                <x-admin.select name="status" label="Status"><option value="">All statuses</option>@foreach(['draft','posted','reversed','cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>@endforeach</x-admin.select>
                <x-admin.select name="payment_account_id" label="Payment account"><option value="">All payment accounts</option>@foreach($paymentAccounts as $account)<option value="{{ $account->id }}" @selected((string) ($filters['payment_account_id'] ?? '') === (string) $account->id)>{{ $account->displayLabel() }}</option>@endforeach</x-admin.select>
                <x-admin.form-input name="transaction_number" label="Transaction number" :value="$filters['transaction_number'] ?? null" placeholder="OC-2026-000001" />
                <x-admin.form-input name="search" label="Search description or reference" :value="$filters['search'] ?? null" />
                <x-admin.form-input name="minimum_amount" type="number" step="0.01" min="0" label="Minimum amount" :value="$filters['minimum_amount'] ?? null" />
                <x-admin.form-input name="maximum_amount" type="number" step="0.01" min="0" label="Maximum amount" :value="$filters['maximum_amount'] ?? null" />
                <div class="flex items-end gap-3 xl:col-span-2"><x-admin.button type="submit" icon="bi-funnel">Filter</x-admin.button><x-admin.button variant="outline" :href="route('admin.accounting.owner-transactions.index')">Clear</x-admin.button><a class="admin-button admin-button-outline" href="{{ route('admin.accounting.owner-transactions.export', ['format' => 'csv', ...request()->query()]) }}"><i class="bi bi-download" aria-hidden="true"></i> CSV</a><a class="admin-button admin-button-outline" href="{{ route('admin.accounting.owner-transactions.export', ['format' => 'xlsx', ...request()->query()]) }}"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Excel</a><a class="admin-button admin-button-outline" href="{{ route('admin.accounting.owner-transactions.export', ['format' => 'pdf', ...request()->query()]) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF</a></div>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-8" title="Owner compensation transactions">
            <p class="mb-5 text-sm text-cream/60">Reporting period: {{ $period['label'] }} ({{ $period['start']->toDateString() }} to {{ $period['end']->toDateString() }}). Reserve balances are calculated from posted General Ledger activity.</p>
            <x-admin.table>
                <x-slot:head><tr><th>Transaction Number</th><th>Date</th><th>Type</th><th>Description</th><th>Amount</th><th>Payment Account</th><th>Status</th><th>Journal</th><th>Reference</th><th>Created By</th><th>Actions</th></tr></x-slot:head>
                @forelse($transactions as $transaction)
                    <tr>
                        <td class="font-medium text-gold">{{ $transaction->transaction_number }}</td>
                        <td>{{ $transaction->transaction_date?->format('d M Y') }}</td>
                        <td><span class="text-sm text-cream">{{ $transaction->typeLabel() }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($transaction->description, 60) }}</td>
                        <td>RM {{ number_format((float) $transaction->amount, 2) }}</td>
                        <td>{{ $transaction->paymentAccount?->displayLabel() ?: '—' }}</td>
                        <td><x-admin.badge :status="$transaction->status" /></td>
                        <td>@if($transaction->journalEntry)<a class="text-gold hover:text-cream" href="{{ route('admin.accounting.journals.show', $transaction->journalEntry) }}">{{ $transaction->journalEntry->entry_number }}</a>@else — @endif</td>
                        <td>{{ $transaction->reference_number ?: '—' }}</td>
                        <td>{{ $transaction->creator?->name ?: '—' }}</td>
                        <td><div class="flex gap-3 whitespace-nowrap"><a class="text-gold hover:text-cream" href="{{ route('admin.accounting.owner-transactions.show', $transaction) }}">View</a>@if($transaction->mayBePosted())<a class="text-gold hover:text-cream" href="{{ route('admin.accounting.owner-transactions.edit', $transaction) }}">Edit</a>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-10 text-center text-cream/60">No owner compensation records match these filters.</td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-5">{{ $transactions->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
