<x-layouts.admin title="Ledger Integrity | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="General Ledger" title="Ledger Integrity Checks" subtitle="Read-only diagnostics. Historical journals are never auto-corrected; use the existing reversal workflow for valid accounting corrections.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.accounting.ledger.index')">General Ledger</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <div class="mt-8 grid gap-5 sm:grid-cols-4">
            <x-admin.stats-card label="Unbalanced Journals" :value="$summary['unbalanced_journals']" :accent="$summary['unbalanced_journals'] > 0" />
            <x-admin.stats-card label="Ledger Debits" :value="'RM '.number_format($summary['total_debits'] / 100, 2)" />
            <x-admin.stats-card label="Ledger Credits" :value="'RM '.number_format($summary['total_credits'] / 100, 2)" />
            <x-admin.stats-card label="Difference" :value="'RM '.number_format($summary['difference'] / 100, 2)" :accent="$summary['difference'] > 0" />
        </div>

        <x-admin.card class="mt-8" title="Integrity results">
            <x-admin.table class="mt-5">
                <x-slot:head><tr><th>Severity</th><th>Check</th><th>Explanation</th><th>Journal</th><th>Recommended Action</th></tr></x-slot:head>
                @forelse($checks as $check)
                    <tr>
                        <td><x-admin.badge :status="$check['severity'] === 'error' ? 'rejected' : 'warning'" :label="str($check['severity'])->title()" /></td>
                        <td>{{ $check['title'] }}</td>
                        <td>{{ $check['description'] }}</td>
                        <td>@if($check['journal'])<a class="text-gold hover:text-cream" href="{{ route('admin.accounting.journals.show', $check['journal']) }}">{{ $check['journal']->entry_number }}</a>@else{{ is_array($check['context']) ? ($check['context']['account'] ?? '—') : '—' }}@endif</td>
                        <td>Review the source and use a documented reversal or adjustment if correction is required.</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-cream/60"><i class="bi bi-shield-check mr-2 text-gold"></i>No integrity issues were detected in posted accounting activity.</td></tr>
                @endforelse
            </x-admin.table>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
