<x-layouts.admin :title="($expense ? 'Edit' : 'New').' Expense | Cherry Bellemont'">
    <x-admin.section width="5xl"><x-admin.page-header eyebrow="Accounting" :title="$expense ? 'Edit Expense Draft' : 'New Expense'" subtitle="Posted expenses are immutable; corrections use a reversal or adjustment journal." />
        <x-admin.card class="mt-8">
            <form id="expense-form" class="grid gap-5 md:grid-cols-2" method="POST" enctype="multipart/form-data" action="{{ $expense ? route('admin.accounting.expenses.update',$expense) : route('admin.accounting.expenses.store') }}">
                @csrf @if($expense) @method('PUT') @endif
                <x-admin.form-input name="expense_date" type="date" label="Expense date" :value="$expense?->expense_date?->toDateString() ?? now()->toDateString()" required /><x-admin.form-input name="accounting_date" type="date" label="Accounting date" :value="$expense?->accounting_date?->toDateString() ?? now()->toDateString()" required />
                <x-admin.select name="expense_category_id" label="Category"><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id',$expense?->expense_category_id)===$category->id)>{{ $category->name }}</option>@endforeach</x-admin.select>
                <x-admin.select name="debit_account_id" label="Expense / debit account" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('debit_account_id',$expense?->debit_account_id)===$account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</x-admin.select>
                <x-admin.select name="payment_account_id" label="Payment account" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('payment_account_id',$expense?->payment_account_id)===$account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</x-admin.select><x-admin.form-input name="supplier" label="Supplier" :value="$expense?->supplier" />
                <x-admin.form-input name="amount" type="number" min="0.01" step="0.01" label="Amount (MYR)" :value="$expense?->amount" required /><x-admin.form-input name="tax_amount" type="number" min="0" step="0.01" label="Tax amount" :value="$expense?->tax_amount" />
                <x-admin.form-input name="payment_method" label="Payment method" :value="$expense?->payment_method" /><x-admin.form-input name="reference_number" label="Reference number" :value="$expense?->reference_number" />
                <x-admin.form-input class="md:col-span-2" name="description" label="Description" :value="$expense?->description" required /><x-admin.textarea class="md:col-span-2" name="notes" label="Notes">{{ old('notes',$expense?->notes) }}</x-admin.textarea><x-admin.form-input class="md:col-span-2" name="receipt" type="file" label="Receipt (PDF, JPG or PNG)" />
            </form>
            <div class="mt-6 flex flex-wrap gap-3"><x-admin.button form="expense-form" type="submit" icon="bi-save">Save Draft</x-admin.button>@if($expense)<form method="POST" action="{{ route('admin.accounting.expenses.post',$expense) }}">@csrf<x-admin.button variant="success" type="submit" icon="bi-journal-check">Post Expense</x-admin.button></form>@endif</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
