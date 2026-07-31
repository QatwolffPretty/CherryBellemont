<x-layouts.admin title="Accounting Overview | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Financial records" title="Accounting Overview" subtitle="Double-entry records and sales activity for {{ strtolower($overview['period']['label']) }}." />
        <div class="mt-8">@include('admin.accounting.partials.period-filter', ['action' => route('admin.accounting.overview'), 'filters' => $filters, 'rangeOptions' => $rangeOptions, 'ledger' => false])</div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach(['gross_sales' => 'Gross Sales', 'discounts' => 'Discounts', 'shipping_revenue' => 'Shipping Revenue', 'refunds' => 'Refunds', 'net_sales' => 'Net Sales', 'money_received' => 'Money Received', 'pending_payments' => 'Pending Payments', 'other_income' => 'Other Income', 'cost_of_goods_sold' => 'Cost of Goods Sold', 'operating_expenses' => 'Operating Expenses', 'net_cash_flow' => 'Net Cash Flow', 'stripe_income' => 'Stripe Income', 'duitnow_income' => 'DuitNow Income', 'owner_compensation' => 'Owner Compensation', 'gross_profit' => 'Gross Profit', 'net_profit' => 'Net Profit', 'business_cash' => 'Business Cash Balance'] as $key => $label)
                <x-admin.stats-card :label="$label" :value="'RM '.number_format($overview['cards'][$key] / 100, 2)" />
            @endforeach
            <x-admin.stats-card label="Paid Orders" :value="$overview['cards']['paid_orders']" />
            <x-admin.stats-card label="Unpaid Orders" :value="$overview['cards']['unpaid_orders']" />
            <x-admin.stats-card label="Unposted Transactions" :value="$overview['cards']['unposted_transactions']" />
        </div>
        <div class="mt-8 grid gap-6 xl:grid-cols-2"><x-admin.card title="Monthly sales and net profit"><div class="admin-chart-canvas mt-6"><canvas id="accounting-sales-chart"></canvas></div></x-admin.card><x-admin.card title="Expenses and cash movement"><div class="admin-chart-canvas mt-6"><canvas id="accounting-expenses-chart"></canvas></div></x-admin.card></div>
        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Recent journal entries"><x-admin.table class="mt-5"><x-slot:head><tr><th>Date</th><th>Entry</th><th>Status</th><th>Action</th></tr></x-slot:head>@forelse($overview['recent']['journals'] as $entry)<tr><td>{{ $entry->transaction_date?->format('d M Y') }}</td><td>{{ $entry->entry_number }}</td><td><x-admin.badge :value="$entry->status" /></td><td><a class="text-gold" href="{{ route('admin.accounting.journals.show', $entry) }}">View</a></td></tr>@empty<tr><td colspan="4" class="text-cream/60">No journal entries yet.</td></tr>@endforelse</x-admin.table></x-admin.card>
            <x-admin.card title="Recent financial activity"><x-admin.table class="mt-5"><x-slot:head><tr><th>Record</th><th>Reference</th><th>Amount</th></tr></x-slot:head>@forelse($overview['recent']['expenses'] as $expense)<tr><td>Expense</td><td>{{ $expense->expense_number }}</td><td>RM {{ number_format($expense->amount + $expense->tax_amount, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-cream/60">No expense records yet.</td></tr>@endforelse</x-admin.table></x-admin.card>
        </div>
        <script id="accounting-chart-data" type="application/json">@json($overview['charts'])</script>
    </x-admin.section>
</x-layouts.admin>
