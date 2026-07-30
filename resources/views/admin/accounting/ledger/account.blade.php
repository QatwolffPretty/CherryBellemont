<x-layouts.admin :title="$ledger['account']->displayLabel().' | General Ledger | Cherry Bellemont'">
    <x-admin.section width="7xl">
        @php($account = $ledger['account'])
        <x-admin.page-header eyebrow="General Ledger" :title="$account->displayLabel()" :subtitle="($account->subtype ?: ucfirst(str_replace('_', ' ', $account->type))).' · '.str($account->normal_balance)->title().' normal balance'">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.index', collect($filters)->except('page')->all())">Account Summary</x-admin.button>
                @foreach(['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)<x-admin.button variant="outline" :href="route('admin.accounting.ledger.account.export', array_merge(['account' => $account, 'format' => $format], collect($filters)->except('page')->all()))" icon="bi-download">{{ $label }}</x-admin.button>@endforeach
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.account.print', array_merge(['account' => $account], collect($filters)->except('page')->all()))" icon="bi-printer" target="_blank">Print</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <p class="mt-5 text-sm text-cream/65">Period: <span class="text-gold">{{ $ledger['period']['label'] }}</span> · {{ $ledger['period']['start']->format('d M Y') }} to {{ $ledger['period']['end']->format('d M Y') }} @if($account->parent) · Parent: {{ $account->parent->displayLabel() }} @endif</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
            <x-admin.stats-card label="Opening Balance" :value="app(\App\Services\GeneralLedgerService::class)->balanceLabel($ledger['opening_balance'], $account)" />
            <x-admin.stats-card label="Total Debits" :value="'RM '.number_format($ledger['total_debit'] / 100, 2)" />
            <x-admin.stats-card label="Total Credits" :value="'RM '.number_format($ledger['total_credit'] / 100, 2)" />
            <x-admin.stats-card label="Net Movement" :value="app(\App\Services\GeneralLedgerService::class)->balanceLabel($ledger['movement'], $account)" />
            <x-admin.stats-card label="Closing Balance" :value="app(\App\Services\GeneralLedgerService::class)->balanceLabel($ledger['closing_balance'], $account)" />
        </div>

        <x-admin.card class="mt-8" title="Account transactions">
            <x-admin.table class="mt-5"><x-slot:head><tr><th>Date</th><th>Posting Date</th><th>Journal</th><th>Reference</th><th>Source</th><th>Description</th><th>Debit</th><th>Credit</th><th>Running Balance</th><th>Status</th><th>Posted By</th></tr></x-slot:head>
                @forelse($ledger['rows'] as $row)
                    <tr>
                        <td>{{ $row['transaction_date'] ? \Carbon\Carbon::parse($row['transaction_date'])->format('d M Y') : '—' }}</td><td>{{ $row['posting_date'] ?: '—' }}</td>
                        <td>@if($row['journal'])<a class="text-gold hover:text-cream" href="{{ route('admin.accounting.journals.show', $row['journal']) }}">{{ $row['journal_number'] }}</a>@else<span class="text-gold">{{ $row['journal_number'] }}</span>@endif</td>
                        <td>{{ $row['reference'] ?: '—' }}</td><td>@if($row['source']['url'])<a class="text-gold hover:text-cream" href="{{ $row['source']['url'] }}">{{ $row['source']['label'] }}</a>@else{{ $row['source']['label'] }}@endif</td><td><span>{{ $row['description'] }}</span>@if($row['line_description'])<p class="mt-1 text-xs text-cream/60">{{ $row['line_description'] }}</p>@endif</td><td>RM {{ number_format($row['debit'] / 100, 2) }}</td><td>RM {{ number_format($row['credit'] / 100, 2) }}</td><td class="font-medium text-gold">{{ $row['running_balance_label'] }}</td><td><x-admin.badge :status="$row['status'] === 'posted' ? 'active' : ($row['status'] === 'reversed' ? 'warning' : 'pending')" :label="str($row['status'])->replace('_', ' ')->title()" /></td><td>{{ $row['posted_by'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-10 text-center text-cream/60">No posted activity exists for this account in the selected period.</td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-5">{{ $ledger['rows']->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
