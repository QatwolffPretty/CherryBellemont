<x-layouts.admin title="Journal Entries | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" title="Journal Entries" subtitle="Draft entries have no financial effect until they are posted.">
            <x-slot:actions><x-admin.button :href="route('admin.accounting.journals.create')" icon="bi-plus-lg">New Journal</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stats-card label="Draft" :value="$summary['draft']" icon="bi-pencil-square" />
            <x-admin.stats-card label="Posted" :value="$summary['posted']" icon="bi-journal-check" />
            <x-admin.stats-card label="Reversed" :value="$summary['reversed']" icon="bi-arrow-counterclockwise" />
            <x-admin.stats-card label="Total Journals" :value="$summary['total']" icon="bi-journal-text" />
        </div>

        <x-admin.card class="mt-8" title="Find a journal">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="GET" action="{{ route('admin.accounting.journals.index') }}">
                <x-admin.form-input name="journal_number" label="Journal number" :value="$filters['journal_number'] ?? null" placeholder="JE-2026-000001" />
                <x-admin.form-input name="description" label="Description" :value="$filters['description'] ?? null" />
                <x-admin.form-input name="from_date" type="date" label="From date" :value="$filters['from_date'] ?? null" />
                <x-admin.form-input name="to_date" type="date" label="To date" :value="$filters['to_date'] ?? null" />
                <x-admin.select name="status" label="Status"><option value="">All statuses</option>@foreach(['draft','posted','reversed','cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>@endforeach</x-admin.select>
                <x-admin.select name="source" label="Source"><option value="">All sources</option>@foreach($sources as $source)<option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ str($source)->replace('_', ' ')->title() }}</option>@endforeach</x-admin.select>
                <x-admin.select name="posted_by" label="Posted by"><option value="">All administrators</option>@foreach($posters as $poster)<option value="{{ $poster->id }}" @selected((string) ($filters['posted_by'] ?? '') === (string) $poster->id)>{{ $poster->name }}</option>@endforeach</x-admin.select>
                <div class="flex items-end gap-3"><x-admin.button type="submit" icon="bi-funnel">Filter</x-admin.button><x-admin.button variant="outline" :href="route('admin.accounting.journals.index')">Clear</x-admin.button></div>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-8" title="Journals">
            <x-admin.table>
                <x-slot:head><tr><th>Journal Number</th><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Status</th><th>Source</th><th>Actions</th></tr></x-slot:head>
                @forelse($entries as $entry)
                    <tr>
                        <td class="font-medium text-gold">{{ $entry->entry_number }}</td>
                        <td>{{ $entry->transaction_date?->format('d M Y') }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($entry->description, 60) }} @if($entry->reference)<p class="mt-1 text-xs text-cream/50">{{ $entry->reference }}</p>@endif</td>
                        <td>RM {{ number_format((float) $entry->total_debit, 2) }}</td>
                        <td>RM {{ number_format((float) $entry->total_credit, 2) }}</td>
                        <td><x-admin.badge :status="$entry->status" /></td>
                        <td>{{ $entry->sourceLabel() }}</td>
                        <td><div class="flex gap-3 whitespace-nowrap"><a class="text-gold hover:text-cream" href="{{ route('admin.accounting.journals.show', $entry) }}">View</a>@if($entry->isDraft())<a class="text-gold hover:text-cream" href="{{ route('admin.accounting.journals.edit', $entry) }}">Edit</a>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-cream/60">No journal entries match these filters. <a class="text-gold" href="{{ route('admin.accounting.journals.create') }}">Create a manual journal</a>.</td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-5">{{ $entries->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
