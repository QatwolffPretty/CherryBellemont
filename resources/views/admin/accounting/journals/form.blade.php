@php
    $lines = old('lines');
    if (!is_array($lines)) {
        $lines = $entry?->lines->map(fn ($line) => ['account_id' => $line->account_id, 'description' => $line->description, 'debit' => $line->debit, 'credit' => $line->credit])->all() ?? [[], []];
    }
@endphp
<x-layouts.admin :title="($entry ? 'Edit '.$entry->entry_number : 'New Journal Entry').' | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Accounting" :title="$entry ? 'Edit '.$entry->entry_number : 'New Journal Entry'" subtitle="Manual journals are saved as balanced drafts. Posting makes them immutable."><x-slot:actions>@if($entry)<x-admin.button variant="outline" :href="route('admin.accounting.journals.show', $entry)">View Journal</x-admin.button>@endif</x-slot:actions></x-admin.page-header>
        <x-admin.card class="mt-8" title="Journal header">
            <form method="POST" action="{{ $entry ? route('admin.accounting.journals.update', $entry) : route('admin.accounting.journals.store') }}" data-journal-form>
                @csrf
                @if($entry)@method('PUT')@endif
                <div class="grid gap-5 md:grid-cols-3"><x-admin.form-input name="transaction_date" type="date" label="Transaction date" :value="$entry?->transaction_date?->toDateString() ?? now()->toDateString()" required /><x-admin.form-input name="reference" label="Reference" :value="$entry?->reference" help="Optional document, payment, or adjustment reference." /><div><label class="admin-label">Status</label><div class="admin-field bg-wine-deep text-cream/70">{{ $entry ? str($entry->status)->title() : 'Draft — pending save' }}</div><p class="mt-2 text-sm text-cream/60">Only balanced drafts can be posted.</p></div><x-admin.form-input class="md:col-span-3" name="description" label="Description" :value="$entry?->description" required /></div>
                @error('lines')<p class="mt-6 border border-red-300/40 bg-red-950/30 px-4 py-3 text-sm text-red-100">{{ $message }}</p>@enderror
                <div class="mt-8" data-journal-lines>
                    <div class="grid gap-3 border-b border-cream/15 pb-3 text-xs uppercase tracking-wider text-cream/60 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1.1fr)_minmax(8rem,.7fr)_minmax(8rem,.7fr)_auto]"><span>Account</span><span>Description</span><span>Debit</span><span>Credit</span><span class="sr-only">Remove</span></div>
                    <div class="mt-4 grid gap-4" data-journal-lines-list>
                        @foreach($lines as $index => $line)
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1.1fr)_minmax(8rem,.7fr)_minmax(8rem,.7fr)_auto]" data-journal-line>
                                <div><label class="sr-only" for="journal-account-{{ $index }}">Account</label><select class="admin-field" id="journal-account-{{ $index }}" name="lines[{{ $index }}][account_id]" required><option value="">Select account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) ($line['account_id'] ?? '') === (string) $account->id)>{{ $account->displayLabel() }}</option>@endforeach</select>@error("lines.$index.account_id")<x-admin.validation-error :message="$message" />@enderror</div>
                                <div><label class="sr-only" for="journal-description-{{ $index }}">Line description</label><input class="admin-field" id="journal-description-{{ $index }}" name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description", $line['description'] ?? '') }}" placeholder="Line description">@error("lines.$index.description")<x-admin.validation-error :message="$message" />@enderror</div>
                                <div><label class="sr-only" for="journal-debit-{{ $index }}">Debit</label><input class="admin-field" data-journal-debit id="journal-debit-{{ $index }}" name="lines[{{ $index }}][debit]" value="{{ old("lines.$index.debit", $line['debit'] ?? '') }}" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">@error("lines.$index.debit")<x-admin.validation-error :message="$message" />@enderror</div>
                                <div><label class="sr-only" for="journal-credit-{{ $index }}">Credit</label><input class="admin-field" data-journal-credit id="journal-credit-{{ $index }}" name="lines[{{ $index }}][credit]" value="{{ old("lines.$index.credit", $line['credit'] ?? '') }}" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">@error("lines.$index.credit")<x-admin.validation-error :message="$message" />@enderror</div>
                                <div class="flex items-center"><button class="admin-button admin-button-outline" type="button" data-remove-journal-line aria-label="Remove journal line"><i class="bi bi-trash"></i></button></div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <template data-journal-line-template><div class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1.1fr)_minmax(8rem,.7fr)_minmax(8rem,.7fr)_auto]" data-journal-line><div><label class="sr-only">Account</label><select class="admin-field" name="lines[__INDEX__][account_id]" required><option value="">Select account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->displayLabel() }}</option>@endforeach</select></div><div><label class="sr-only">Line description</label><input class="admin-field" name="lines[__INDEX__][description]" placeholder="Line description"></div><div><label class="sr-only">Debit</label><input class="admin-field" data-journal-debit name="lines[__INDEX__][debit]" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></div><div><label class="sr-only">Credit</label><input class="admin-field" data-journal-credit name="lines[__INDEX__][credit]" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></div><div class="flex items-center"><button class="admin-button admin-button-outline" type="button" data-remove-journal-line aria-label="Remove journal line"><i class="bi bi-trash"></i></button></div></div></template>
                <div class="mt-6 flex flex-wrap items-center justify-between gap-5 border-y border-cream/15 py-5"><x-admin.button variant="outline" type="button" icon="bi-plus-lg" data-add-journal-line>Add Line</x-admin.button><div class="grid gap-2 text-sm text-right sm:grid-cols-3 sm:items-center sm:gap-6"><span>Debit: <strong data-journal-debit-total>RM 0.00</strong></span><span>Credit: <strong data-journal-credit-total>RM 0.00</strong></span><span class="text-cream/70" data-journal-balance-message>Enter balanced amounts.</span></div></div>
                <div class="mt-6 flex flex-wrap gap-3"><x-admin.button type="submit" icon="bi-save">Save Balanced Draft</x-admin.button><x-admin.button variant="outline" :href="$entry ? route('admin.accounting.journals.show', $entry) : route('admin.accounting.journals.index')">Cancel</x-admin.button></div>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
