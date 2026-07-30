<x-admin.card>
    <form class="grid gap-4 md:grid-cols-4 xl:grid-cols-6" method="GET" action="{{ $action }}">
        <x-admin.select class="mt-0" name="range" label="Reporting period">
            @foreach($rangeOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['range'] ?? '') === $value)>{{ $label }}</option>@endforeach
        </x-admin.select>
        <x-admin.form-input class="mt-0" name="from_date" type="date" label="From" :value="$filters['from_date'] ?? null" />
        <x-admin.form-input class="mt-0" name="to_date" type="date" label="To" :value="$filters['to_date'] ?? null" />
        @if($ledger)
            <x-admin.select class="mt-0" name="account_id" label="Account"><option value="">All accounts</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(($filters['account_id'] ?? null) == $account->id)>{{ $account->code }} — {{ $account->name }}</option>@endforeach</x-admin.select>
            <x-admin.select class="mt-0" name="account_type" label="Account type"><option value="">All account types</option>@foreach(['asset'=>'Assets','liability'=>'Liabilities','equity'=>'Equity','revenue'=>'Revenue','cost_of_goods_sold'=>'Cost of Goods Sold','expense'=>'Expenses'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['account_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-admin.select>
            <x-admin.select class="mt-0" name="movement" label="Movement"><option value="">All movements</option><option value="debit" @selected(($filters['movement'] ?? '') === 'debit')>Debit</option><option value="credit" @selected(($filters['movement'] ?? '') === 'credit')>Credit</option></x-admin.select>
            <x-admin.form-input class="mt-0" name="reference" label="Reference / journal" :value="$filters['reference'] ?? null" />
            <x-admin.form-input class="mt-0" name="source_type" label="Source type" :value="$filters['source_type'] ?? null" />
        @endif
        <div class="flex items-end"><x-admin.button class="w-full" type="submit" icon="bi-funnel">Apply</x-admin.button></div>
    </form>
</x-admin.card>
