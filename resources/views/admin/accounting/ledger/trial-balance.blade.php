<x-layouts.admin title="Trial Balance | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="General Ledger" title="Trial Balance" subtitle="Read-only balances derived from the same posted journal activity and opening-balance treatment as the General Ledger.">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.accounting.ledger.index', collect($filters)->except('page')->all())">General Ledger</x-admin.button>
                @foreach(['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                    <x-admin.button variant="outline" :href="route('admin.accounting.ledger.trial-balance.export', array_merge(['format' => $format], collect($filters)->except('page')->all()))" icon="bi-download">{{ $label }}</x-admin.button>
                @endforeach
            </x-slot:actions>
        </x-admin.page-header>
        <p class="mt-5 text-sm text-cream/65">{{ $trialBalance['period']['label'] }} · {{ $trialBalance['period']['start']->format('d M Y') }} to {{ $trialBalance['period']['end']->format('d M Y') }}</p>
        @if($trialBalance['difference'] !== 0)<div class="mt-5 border border-red-300/50 bg-red-950/30 px-5 py-4 text-red-100"><i class="bi bi-exclamation-triangle mr-2"></i>Trial balance difference: RM {{ number_format($trialBalance['difference'] / 100, 2) }}. Review the General Ledger integrity report; no automatic balancing adjustment has been made.</div>@endif
        <div class="mt-8 grid gap-5 sm:grid-cols-3"><x-admin.stats-card label="Debit Balances" :value="'RM '.number_format($trialBalance['total_debit'] / 100, 2)" /><x-admin.stats-card label="Credit Balances" :value="'RM '.number_format($trialBalance['total_credit'] / 100, 2)" /><x-admin.stats-card label="Difference" :value="'RM '.number_format($trialBalance['difference'] / 100, 2)" :accent="$trialBalance['difference'] !== 0" /></div>
        <x-admin.card class="mt-8" title="Trial balance accounts">
            <x-admin.table class="mt-5">
                <x-slot:head><tr><th>Account Code</th><th>Account Name</th><th>Debit Balance</th><th>Credit Balance</th></tr></x-slot:head>
                @forelse($trialBalance['rows'] as $row)
                    <tr><td class="text-gold">{{ $row['account']->code }}</td><td>{{ $row['account']->name }}</td><td>RM {{ number_format($row['debit_balance'] / 100, 2) }}</td><td>RM {{ number_format($row['credit_balance'] / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-cream/60">No accounts are available for the selected period.</td></tr>
                @endforelse
                <tfoot><tr class="font-semibold text-cream"><td colspan="2">Totals</td><td>RM {{ number_format($trialBalance['total_debit'] / 100, 2) }}</td><td>RM {{ number_format($trialBalance['total_credit'] / 100, 2) }}</td></tr></tfoot>
            </x-admin.table>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
