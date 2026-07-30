<x-layouts.admin :title="$transaction->transaction_number.' | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Owner Compensation" :title="$transaction->transaction_number" :subtitle="$transaction->typeLabel().' — '.$transaction->description">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.accounting.owner-transactions.index')">All Transactions</x-admin.button>
                @if($transaction->mayBePosted())
                    <x-admin.button variant="outline" :href="route('admin.accounting.owner-transactions.edit', $transaction)" icon="bi-pencil">Edit Draft</x-admin.button>
                    <form method="POST" action="{{ route('admin.accounting.owner-transactions.cancel', $transaction) }}">@csrf<x-admin.button variant="danger" type="submit" icon="bi-x-circle">Cancel Draft</x-admin.button></form>
                    <form method="POST" action="{{ route('admin.accounting.owner-transactions.post', $transaction) }}">@csrf<x-admin.button variant="success" type="submit" icon="bi-journal-check">Post Transaction</x-admin.button></form>
                @elseif($transaction->status === 'posted')
                    <form class="flex gap-3" method="POST" action="{{ route('admin.accounting.owner-transactions.reverse', $transaction) }}">@csrf<input class="admin-field w-52" name="reason" maxlength="500" placeholder="Reversal reason (optional)"><x-admin.button variant="danger" type="submit" icon="bi-arrow-counterclockwise">Reverse</x-admin.button></form>
                @endif
            </x-slot:actions>
        </x-admin.page-header>
        @foreach(['transaction', 'amount', 'accounts'] as $error)
            @error($error)
                <p class="mt-6 border border-red-300/40 bg-red-950/30 px-4 py-3 text-sm text-red-100">{{ $message }}</p>
            @enderror
        @endforeach
        @foreach($postingWarnings as $warning)
            <p class="mt-6 border border-gold/50 bg-wine-deep px-4 py-3 text-sm text-gold"><i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>{{ $warning }}</p>
        @endforeach
        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4"><x-admin.stats-card label="Status" :value="str($transaction->status)->title()" /><x-admin.stats-card label="Amount" :value="'RM '.number_format((float) $transaction->amount, 2)" /><x-admin.stats-card label="Debit Account" :value="$transaction->debitAccount?->code ?: '—'" /><x-admin.stats-card label="Credit Account" :value="$transaction->creditAccount?->code ?: '—'" /></div>
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card class="lg:col-span-2" title="Transaction details"><dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2"><div><dt class="admin-label">Date</dt><dd>{{ $transaction->transaction_date?->format('d M Y') }}</dd></div><div><dt class="admin-label">Type</dt><dd>{{ $transaction->typeLabel() }}</dd></div><div><dt class="admin-label">Payment account</dt><dd>{{ $transaction->paymentAccount?->displayLabel() ?: 'Not applicable' }}</dd></div><div><dt class="admin-label">Payment method</dt><dd>{{ $transaction->payment_method ?: '—' }}</dd></div><div><dt class="admin-label">Reference</dt><dd>{{ $transaction->reference_number ?: '—' }}</dd></div><div><dt class="admin-label">Journal</dt><dd>@if($transaction->journalEntry)<a class="text-gold" href="{{ route('admin.accounting.journals.show', $transaction->journalEntry) }}">{{ $transaction->journalEntry->entry_number }}</a>@else Not posted @endif</dd></div><div><dt class="admin-label">Debit</dt><dd>{{ $transaction->debitAccount?->displayLabel() ?: 'Resolved when posted' }}</dd></div><div><dt class="admin-label">Credit</dt><dd>{{ $transaction->creditAccount?->displayLabel() ?: 'Resolved when posted' }}</dd></div></dl><div class="mt-6 border-t border-cream/15 pt-5"><p class="admin-label">Description</p><p class="mt-2 whitespace-pre-line text-cream/85">{{ $transaction->description }}</p>@if($transaction->notes)<p class="mt-5 admin-label">Internal notes</p><p class="mt-2 whitespace-pre-line text-cream/70">{{ $transaction->notes }}</p>@endif</div></x-admin.card>
            <x-admin.card title="Audit"><dl class="space-y-4 text-sm"><div><dt class="admin-label">Created</dt><dd>{{ $transaction->created_at?->format('d M Y, H:i') }} @if($transaction->creator) by {{ $transaction->creator->name }}@endif</dd></div><div><dt class="admin-label">Posted</dt><dd>{{ $transaction->posted_at?->format('d M Y, H:i') ?: '—' }} @if($transaction->poster) by {{ $transaction->poster->name }}@endif</dd></div><div><dt class="admin-label">Reversed</dt><dd>{{ $transaction->reversed_at?->format('d M Y, H:i') ?: '—' }}</dd></div><div><dt class="admin-label">Attachment</dt><dd>@if($transaction->attachmentUrl())<a class="text-gold" href="{{ $transaction->attachmentUrl() }}">Download privately</a>@else — @endif</dd></div></dl></x-admin.card>
        </div>
        @if($transaction->journalEntry)
            <x-admin.card class="mt-6" title="Posted journal lines">
                <x-admin.table class="mt-5">
                    <x-slot:head><tr><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th></tr></x-slot:head>
                    @foreach($transaction->journalEntry->lines as $line)
                        <tr><td>{{ $line->account?->displayLabel() ?: '—' }}</td><td>{{ $line->description ?: '—' }}</td><td>RM {{ number_format((float) $line->debit, 2) }}</td><td>RM {{ number_format((float) $line->credit, 2) }}</td></tr>
                    @endforeach
                </x-admin.table>
            </x-admin.card>
        @endif
        @if($transaction->reversalTransaction)<x-admin.card class="mt-6" title="Reversal"><p>This transaction was reversed by <a class="text-gold" href="{{ route('admin.accounting.owner-transactions.show', $transaction->reversalTransaction) }}">{{ $transaction->reversalTransaction->transaction_number }}</a>. Its journal remains visible in the General Ledger.</p></x-admin.card>@endif
    </x-admin.section>
</x-layouts.admin>
