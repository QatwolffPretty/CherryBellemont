<x-layouts.admin title="Income | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" title="Income" subtitle="Paid-order income is system generated and cannot be edited here." />
        <div class="mt-8">@include('admin.accounting.partials.period-filter', ['action' => route('admin.accounting.income.index'), 'filters' => $filters, 'rangeOptions' => $rangeOptions, 'ledger' => false])</div>
        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Posted income">
                <x-admin.table class="mt-5"><x-slot:head><tr><th>Date</th><th>Reference</th><th>Source</th><th>Amount</th><th>Journal</th></tr></x-slot:head>
                    @forelse($incomeEntries as $entry)<tr><td>{{ $entry->transaction_date?->format('d M Y') }}</td><td>{{ $entry->reference ?: '—' }}</td><td>{{ str($entry->source_type)->replace('_',' ')->title() }}</td><td>RM {{ number_format($entry->total_credit,2) }}</td><td><a class="text-gold" href="{{ route('admin.accounting.journals.show',$entry) }}">{{ $entry->entry_number }}</a></td></tr>@empty<tr><td colspan="5" class="text-cream/60">No posted income in this period.</td></tr>@endforelse
                </x-admin.table><div class="mt-5">{{ $incomeEntries->links() }}</div>
            </x-admin.card>
            <x-admin.card title="Record Other Income">
                <form class="mt-5 grid gap-4" method="POST" action="{{ route('admin.accounting.income.store') }}">@csrf
                    <x-admin.form-input name="transaction_date" type="date" label="Income date" :value="now()->toDateString()" required />
                    <x-admin.select name="deposit_account_id" label="Deposit account" required>@foreach($depositAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</x-admin.select>
                    <x-admin.select name="revenue_account_id" label="Revenue account" required>@foreach($revenueAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</x-admin.select>
                    <x-admin.form-input name="amount" type="number" step="0.01" min="0.01" label="Amount (MYR)" required />
                    <x-admin.form-input name="reference" label="Reference" />
                    <x-admin.textarea name="description" label="Description" required />
                    <x-admin.button type="submit" icon="bi-plus-circle">Post income</x-admin.button>
                </form>
            </x-admin.card>
        </div>
    </x-admin.section>
</x-layouts.admin>
