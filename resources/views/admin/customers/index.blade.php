<x-layouts.admin title="Customers | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Customer intelligence" title="Customers" subtitle="Guest and registered customers are grouped by their normalized order email address.">
            <x-slot:actions>
                <x-admin.button variant="outline" icon="bi-download" :href="route('admin.customers.export', request()->query())">Export CSV</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mt-8">
            <form class="grid gap-4 lg:grid-cols-[1fr_16rem_auto]" method="GET" action="{{ route('admin.customers.index') }}">
                <x-admin.form-input class="mt-0" name="search" label="Search customers" :value="$filters['search']" placeholder="Name, email, phone, or order number" />
                <x-admin.select class="mt-0" name="filter" label="Customer filter">
                    @foreach($filterOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['filter'] === $value)>{{ $label }}</option>
                    @endforeach
                </x-admin.select>
                <div class="flex items-end"><x-admin.button class="w-full" type="submit" icon="bi-funnel">Apply</x-admin.button></div>
            </form>
        </x-admin.card>

        <x-admin.card title="Customer records" class="mt-8">
            <x-admin.table class="mt-6">
                <x-slot:head><tr><th>Customer</th><th>Account</th><th>Orders</th><th>Paid spend</th><th>Average order</th><th>Last order</th><th>Newsletter</th><th></th></tr></x-slot:head>
                @forelse($customers as $customer)
                    <tr>
                        <td>{{ $customer->customer_name ?: 'Customer' }}<br><small>{{ $customer->customer_email }}</small><br><small>{{ $customer->customer_phone ?: 'No phone recorded' }}</small></td>
                        <td><x-admin.badge :status="$customer->registered ? 'active' : 'pending'" :label="$customer->registered ? 'Registered' : 'Guest'" /></td>
                        <td>{{ $customer->total_orders }} total<br><small>{{ $customer->paid_orders }} paid</small></td>
                        <td>RM {{ number_format($customer->total_spent, 2) }}</td>
                        <td>RM {{ number_format($customer->average_order_value, 2) }}</td>
                        <td>{{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d M Y, H:i') : '—' }}</td>
                        <td><x-admin.badge :status="$customer->newsletter_status === 'subscribed' ? 'active' : 'pending'" :label="$customer->newsletter_status" /></td>
                        <td><x-admin.button variant="outline" :href="route('admin.customers.show', ['email' => $customer->customer_email])">View</x-admin.button></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty-state title="No customer records found." description="Customers appear after orders with an email address are placed." icon="bi-people" /></td></tr>
                @endforelse
            </x-admin.table>

            @if($customers->hasPages())<div class="mt-6">{{ $customers->links() }}</div>@endif
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
