<x-layouts.admin title="Expense {{ $expense->expense_number }} | Cherry Bellemont">
    <x-admin.section width="5xl">
        <x-admin.page-header eyebrow="Accounting · Expense" :title="$expense->expense_number" :subtitle="$expense->description">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.accounting.expenses.index')" icon="bi-arrow-left">Expenses</x-admin.button></x-slot:actions>
        </x-admin.page-header>
        <x-admin.card class="mt-8">
            <dl class="grid gap-5 text-sm md:grid-cols-2">
                <div><dt class="text-cream/55">Status</dt><dd class="mt-1"><x-admin.badge :status="$expense->status" /></dd></div>
                <div><dt class="text-cream/55">Date</dt><dd class="mt-1">{{ $expense->expense_date?->format('d M Y') }}</dd></div>
                <div><dt class="text-cream/55">Supplier / payee</dt><dd class="mt-1">{{ $expense->supplier ?: '—' }}</dd></div>
                <div><dt class="text-cream/55">Amount</dt><dd class="mt-1">RM {{ number_format($expense->amount + $expense->tax_amount, 2) }}</dd></div>
                <div><dt class="text-cream/55">Category</dt><dd class="mt-1">{{ $expense->category?->name ?: '—' }}</dd></div>
                <div><dt class="text-cream/55">Payment account</dt><dd class="mt-1">{{ $expense->paymentAccount?->displayLabel() ?: '—' }}</dd></div>
                <div><dt class="text-cream/55">Expense account</dt><dd class="mt-1">{{ $expense->debitAccount?->displayLabel() ?: '—' }}</dd></div>
                <div><dt class="text-cream/55">Reference</dt><dd class="mt-1">{{ $expense->reference_number ?: '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-cream/55">Notes</dt><dd class="mt-1 whitespace-pre-line">{{ $expense->notes ?: '—' }}</dd></div>
            </dl>
            @if($expense->receipt_path)<div class="mt-6"><x-admin.button variant="outline" :href="route('admin.accounting.expenses.receipt.download',$expense)" icon="bi-download">Download Receipt</x-admin.button></div>@endif
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
