<x-layouts.admin :title="($transaction ? 'Edit' : 'New').' Owner Compensation | Cherry Bellemont'">
    <x-admin.section width="5xl">
        <x-admin.page-header eyebrow="Accounting" :title="$transaction ? 'Edit Owner Compensation Draft' : 'New Owner Compensation'" subtitle="Posting accounts are resolved from Financial Settings on the server. Salary affects Profit &amp; Loss; drawings, capital and reserves do not.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.accounting.owner-transactions.index')">All Transactions</x-admin.button></x-slot:actions>
        </x-admin.page-header>
        <x-admin.card class="mt-8">
            <form id="owner-compensation-form" class="grid gap-5 md:grid-cols-2" method="POST" enctype="multipart/form-data" action="{{ $transaction ? route('admin.accounting.owner-transactions.update', $transaction) : route('admin.accounting.owner-transactions.store') }}">
                @csrf @if($transaction) @method('PUT') @endif
                <x-admin.form-input name="transaction_date" type="date" label="Transaction date" :value="$transaction?->transaction_date?->toDateString() ?? now()->toDateString()" required />
                <x-admin.select name="transaction_type" label="Transaction type" required>@foreach($types as $value => $label)<option value="{{ $value }}" @selected(old('transaction_type', $transaction?->canonicalType()) === $value)>{{ $label }}</option>@endforeach</x-admin.select>
                <x-admin.form-input name="amount" type="number" step="0.01" min="0.01" label="Amount (MYR)" :value="$transaction?->amount" required />
                <x-admin.select name="payment_account_id" label="Cash or Bank account"><option value="">Not applicable for reserve allocation</option>@foreach($paymentAccounts as $account)<option value="{{ $account->id }}" @selected((string) old('payment_account_id', $transaction?->payment_account_id) === (string) $account->id)>{{ $account->displayLabel() }}</option>@endforeach</x-admin.select>
                <x-admin.form-input name="payment_method" label="Payment method" :value="$transaction?->payment_method" placeholder="Bank transfer, cash, etc." />
                <x-admin.form-input name="reference_number" label="Reference number" :value="$transaction?->reference_number" />
                <x-admin.form-input class="md:col-span-2" name="description" label="Description" :value="$transaction?->description" required />
                <div class="md:col-span-2"><label class="admin-label" for="attachment">Supporting document</label><input id="attachment" class="admin-field mt-2 block w-full" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"><p class="mt-2 text-sm text-cream/55">Optional PDF or image, up to 5 MB. Files are stored privately.</p>@if($transaction?->attachmentUrl())<a class="mt-2 inline-block text-sm text-gold hover:text-cream" href="{{ $transaction->attachmentUrl() }}"><i class="bi bi-paperclip" aria-hidden="true"></i> Download current attachment</a>@endif</div>
                <x-admin.textarea class="md:col-span-2" name="notes" label="Internal notes">{{ old('notes', $transaction?->notes) }}</x-admin.textarea>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-6" title="Posting preview">
            <div id="owner-compensation-preview" data-mappings="{{ json_encode($preview) }}" class="grid gap-5 md:grid-cols-2">
                <div><p class="admin-label">Debit</p><p id="owner-compensation-debit" class="mt-2 text-cream">—</p></div>
                <div><p class="admin-label">Credit</p><p id="owner-compensation-credit" class="mt-2 text-cream">—</p></div>
            </div>
            <p id="owner-compensation-impact" class="mt-5 text-sm text-cream/60"></p>
            <p class="mt-3 text-sm text-gold">This preview is informational. Account mappings, account status and balance safeguards are checked again when posting.</p>
        </x-admin.card>

        <div class="mt-6 flex flex-wrap gap-3"><x-admin.button form="owner-compensation-form" type="submit" icon="bi-save">Save Draft</x-admin.button>@if($transaction)<x-admin.button variant="outline" :href="route('admin.accounting.owner-transactions.show', $transaction)">Cancel</x-admin.button>@endif</div>
    </x-admin.section>
    <script>
        (() => {
            const type = document.querySelector('[name="transaction_type"]');
            const preview = document.getElementById('owner-compensation-preview');
            if (!type || !preview) return;
            const mappings = JSON.parse(preview.dataset.mappings || '{}');
            const debit = document.getElementById('owner-compensation-debit');
            const credit = document.getElementById('owner-compensation-credit');
            const impact = document.getElementById('owner-compensation-impact');
            const impacts = { salary: 'Owner Salary is an operating expense and affects Profit & Loss.', drawing: 'Owner Drawing is an equity withdrawal and does not affect Profit & Loss.', capital_contribution: 'Owner Capital Contribution is equity, not revenue.', business_reserve: 'Business Reserve Allocation is an equity reclassification, not an expense.', emergency_reserve: 'Emergency Reserve Allocation is an equity reclassification, not an expense.' };
            const update = () => { const mapping = mappings[type.value] || {}; debit.textContent = mapping.debit || '—'; credit.textContent = mapping.credit || '—'; impact.textContent = impacts[type.value] || ''; };
            type.addEventListener('change', update); update();
        })();
    </script>
</x-layouts.admin>
