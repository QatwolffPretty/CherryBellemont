@php
    $selectedType = old('type', $account?->type ?? 'asset');
    $selectedSubtype = old('subtype', $account?->subtype);
    $selectedBalance = old('normal_balance', $account?->normal_balance ?? \App\Support\AccountingCatalog::defaultNormalBalance($selectedType, $selectedSubtype));
    $systemLocked = (bool) $account?->is_system;
@endphp
<x-layouts.admin :title="($account ? 'Edit' : 'Create').' Account | Cherry Bellemont'">
    <x-admin.section width="5xl">
        <x-admin.page-header eyebrow="Accounting" :title="$account ? 'Edit Account' : 'Create Account'" subtitle="Account codes are permanent identifiers. Use deactivation rather than deleting accounts with financial history." />

        @if($systemLocked)<div class="mt-8 border border-gold/40 bg-wine-deep px-5 py-4 text-sm text-cream/80"><i class="bi bi-shield-lock mr-2 text-gold"></i>This is a required system account. Its code, type, subtype and normal balance are protected.</div>@endif

        <x-admin.card class="mt-8" title="Account details">
            <form class="grid gap-5 md:grid-cols-2" method="POST" action="{{ $account ? route('admin.accounting.accounts.update', $account) : route('admin.accounting.accounts.store') }}" data-account-form>
                @csrf
                @if($account)@method('PUT')@endif
                @if($systemLocked)<input type="hidden" name="code" value="{{ $account->code }}"><input type="hidden" name="type" value="{{ $account->type }}"><input type="hidden" name="subtype" value="{{ $account->subtype }}"><input type="hidden" name="normal_balance" value="{{ $account->normal_balance }}"><input type="hidden" name="parent_id" value="{{ $account->parent_id }}">@endif
                <x-admin.form-input name="code" label="Account code" :value="$account?->code" help="Digits only, for example 6010." :disabled="$systemLocked" required />
                <x-admin.form-input name="name" label="Account name" :value="$account?->name" required />
                <x-admin.select name="type" label="Account type" :disabled="$systemLocked" required data-account-type>
                    @foreach($types as $value => $label)<option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>@endforeach
                </x-admin.select>
                <x-admin.select name="subtype" label="Account subtype" :disabled="$systemLocked" help="Subtypes are filtered to the selected account type." data-account-subtype>
                    <option value="">Select a subtype</option>
                    @foreach($subtypesByType as $type => $subtypes)<optgroup label="{{ $types[$type] }}" data-type="{{ $type }}">@foreach($subtypes as $subtype)<option value="{{ $subtype }}" data-type="{{ $type }}" @selected($selectedSubtype === $subtype)>{{ $subtype }}</option>@endforeach</optgroup>@endforeach
                </x-admin.select>
                <div><x-admin.select name="normal_balance" label="Normal balance" :disabled="$systemLocked" help="The usual balance is suggested automatically. Contra accounts may use debit." required data-normal-balance><option value="debit" @selected($selectedBalance === 'debit')>Debit</option><option value="credit" @selected($selectedBalance === 'credit')>Credit</option></x-admin.select><p class="mt-2 hidden text-sm text-gold" data-contra-warning>Contra accounts normally carry a debit balance.</p></div>
                <x-admin.select name="parent_id" label="Parent account" :disabled="$systemLocked" help="Only active accounts with the same type can be selected." data-account-parent>
                    <option value="">No parent account</option>
                    @foreach($parents as $parent)<option value="{{ $parent->id }}" data-type="{{ $parent->type }}" @selected((string) old('parent_id', $account?->parent_id) === (string) $parent->id)>{{ $parent->displayLabel() }}</option>@endforeach
                </x-admin.select>
                <x-admin.form-input name="opening_balance" type="number" step="0.01" label="Opening balance" :value="$account?->opening_balance ?? '0.00'" help="A non-zero balance requires its accounting date." />
                <x-admin.form-input name="opening_balance_date" type="date" label="Opening balance date" :value="$account?->opening_balance_date?->toDateString()" />
                <x-admin.textarea class="md:col-span-2" name="description" label="Description" help="Explain the account’s intended use for future administrators.">{{ old('description', $account?->description) }}</x-admin.textarea>
                <div class="grid gap-4 md:col-span-2 md:grid-cols-2"><label class="flex items-center gap-3 text-sm text-cream/90"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account?->is_active ?? true))> Active account</label><label class="flex items-center gap-3 text-sm text-cream/90"><input type="hidden" name="allow_manual_posting" value="0"><input type="checkbox" name="allow_manual_posting" value="1" @checked(old('allow_manual_posting', $account?->allow_manual_posting ?? true))> Allow manual posting</label></div>
                <fieldset class="border border-cream/15 px-4 py-4 md:col-span-2"><legend class="px-2 text-sm font-semibold text-gold">Cash Flow classification</legend><p class="mb-4 text-sm text-cream/60">Use only for debit-normal asset accounts. Clearing accounts can be included as cash equivalents without double counting settlements.</p><div class="grid gap-4 md:grid-cols-2"><label class="flex items-center gap-3 text-sm text-cream/90"><input type="hidden" name="is_cash_account" value="0"><input type="checkbox" name="is_cash_account" value="1" @checked(old('is_cash_account', $account?->is_cash_account ?? false))> Cash or bank account</label><label class="flex items-center gap-3 text-sm text-cream/90"><input type="hidden" name="is_cash_equivalent" value="0"><input type="checkbox" name="is_cash_equivalent" value="1" @checked(old('is_cash_equivalent', $account?->is_cash_equivalent ?? false))> Cash equivalent</label><label class="flex items-center gap-3 text-sm text-cream/90"><input type="hidden" name="is_clearing_account" value="0"><input type="checkbox" name="is_clearing_account" value="1" @checked(old('is_clearing_account', $account?->is_clearing_account ?? false))> Payment clearing account</label><label class="flex items-center gap-3 text-sm text-cream/90"><input type="hidden" name="cash_flow_enabled" value="0"><input type="checkbox" name="cash_flow_enabled" value="1" @checked(old('cash_flow_enabled', $account?->cash_flow_enabled ?? false))> Include in Cash Flow reports</label></div></fieldset>
                <div class="md:col-span-2 flex flex-wrap gap-3"><x-admin.button type="submit" icon="bi-save">Save Account</x-admin.button><x-admin.button variant="outline" :href="$account ? route('admin.accounting.accounts.show', $account) : route('admin.accounting.accounts.index')">Cancel</x-admin.button></div>
            </form>
        </x-admin.card>
        <script id="account-form-data" type="application/json">@json(['defaults' => array_map(fn ($type) => \App\Support\AccountingCatalog::defaultNormalBalance($type), array_keys($types)), 'contraSubtypes' => ['Sales Discounts', 'Sales Returns', 'Contra Revenue', 'Owner Drawings', 'Drawing']])</script>
    </x-admin.section>
</x-layouts.admin>
