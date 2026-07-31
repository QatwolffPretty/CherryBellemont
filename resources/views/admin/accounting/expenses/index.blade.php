<x-layouts.admin title="Expenses | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" title="Expenses" subtitle="Draft, approve, post, reverse, or safely void expenses through balanced journals.">
            <x-slot:actions>
                <x-admin.button :href="route('admin.accounting.expenses.create')" icon="bi-plus-lg">New Expense</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mt-8">
            <form class="grid gap-4 md:grid-cols-3" method="GET">
                <x-admin.select class="mt-0" name="status" label="Status">
                    <option value="">All statuses</option>
                    @foreach(['draft', 'approved', 'posted', 'reversed', 'voided'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->title() }}</option>
                    @endforeach
                </x-admin.select>
                <x-admin.form-input class="mt-0" name="search" label="Search" :value="request('search')" />
                <div class="flex items-end"><x-admin.button type="submit" icon="bi-funnel">Filter</x-admin.button></div>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-8">
            <x-admin.table>
                <x-slot:head><tr><th>Date</th><th>Reference</th><th>Supplier</th><th>Amount</th><th>Status</th><th>Journal</th><th>Action</th></tr></x-slot:head>
                @forelse($expenses as $expense)
                    <tr>
                        <td>{{ $expense->expense_date?->format('d M Y') }}</td>
                        <td>{{ $expense->expense_number }}</td>
                        <td>{{ $expense->supplier ?: '—' }}</td>
                        <td>RM {{ number_format($expense->amount + $expense->tax_amount, 2) }}</td>
                        <td><x-admin.badge :status="$expense->status" /></td>
                        <td>@if($expense->journalEntry)<a class="text-gold" href="{{ route('admin.accounting.journals.show', $expense->journalEntry) }}">{{ $expense->journalEntry->entry_number }}</a>@else — @endif</td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a class="text-gold" href="{{ route('admin.accounting.expenses.show', $expense) }}">View</a>
                                @if($expense->status === 'draft')
                                    <a class="text-gold" href="{{ route('admin.accounting.expenses.edit', $expense) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.accounting.expenses.approve', $expense) }}">@csrf<x-admin.button variant="outline" type="submit">Approve</x-admin.button></form>
                                    <form method="POST" action="{{ route('admin.accounting.expenses.void', $expense) }}">@csrf<x-admin.button variant="outline" type="submit">Void</x-admin.button></form>
                                @elseif($expense->status === 'approved')
                                    <form method="POST" action="{{ route('admin.accounting.expenses.post', $expense) }}">@csrf<x-admin.button variant="success" type="submit">Post</x-admin.button></form>
                                    <form method="POST" action="{{ route('admin.accounting.expenses.void', $expense) }}">@csrf<x-admin.button variant="outline" type="submit">Void</x-admin.button></form>
                                @elseif($expense->status === 'posted')
                                    <form method="POST" action="{{ route('admin.accounting.expenses.reverse', $expense) }}">@csrf<x-admin.button variant="outline" type="submit">Reverse</x-admin.button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-cream/60">No expense records found.</td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-5">{{ $expenses->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
