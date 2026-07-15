<x-layouts.admin title="Orders | Atelier">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Order management" title="Orders" />

        <form class="mt-6 grid gap-3 md:grid-cols-6" method="GET">
            <x-admin.form-input name="search" :value="request('search')" placeholder="Order, name, email, phone" aria-label="Search orders" class="mt-0" />
            <x-admin.select name="payment_provider" aria-label="Filter payment provider" class="mt-0">
                <option value="">All payment providers</option>
                <option value="duitnow" @selected(request('payment_provider') === 'duitnow')>DuitNow</option>
                <option value="stripe" @selected(request('payment_provider') === 'stripe')>Stripe</option>
            </x-admin.select>
            <x-admin.select name="payment_status" aria-label="Filter payment status" class="mt-0">
                <option value="">All payments</option>
                @foreach(['pending', 'paid', 'failed', 'refunded'] as $status)
                    <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.select name="order_status" aria-label="Filter fulfilment status" class="mt-0">
                <option value="">All fulfilment states</option>
                @foreach(['pending', 'payment_review', 'paid', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('order_status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.select name="delivery_method_id" aria-label="Filter delivery method" class="mt-0">
                <option value="">All delivery methods</option>
                @foreach($deliveryMethods as $method)
                    <option value="{{ $method->id }}" @selected((string) request('delivery_method_id') === (string) $method->id)>{{ $method->name }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.button type="submit">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-8">
            <x-slot:head>
                <tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Provider</th><th>Payment</th><th>Fulfilment</th><th>Delivery</th><th>Courier / tracking</th><th></th></tr>
            </x-slot:head>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->order_number ?? $order->number }}</td>
                    <td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }} &middot; {{ $order->customer_phone }}</small></td>
                    <td>{{ $order->created_at?->format('d M Y') }}</td>
                    <td>RM {{ number_format($order->total, 2) }}</td>
                    <td><x-admin.badge :status="$order->payment_provider ?? $order->payment_method" :label="$order->payment_provider ?? $order->payment_method" /></td>
                    <td><span class="block pb-1 text-xs text-cream/60">Payment</span><x-admin.badge :status="$order->payment_status" /></td>
                    <td><span class="block pb-1 text-xs text-cream/60">Fulfilment</span><x-admin.badge :status="$order->order_status" /></td>
                    <td>{{ $order->shipping_method_name ?? '—' }}</td>
                    <td>{{ $order->courier_name ?: '—' }}<br><small>{{ $order->tracking_number }}</small></td>
                    <td><x-admin.button variant="outline" :href="route('admin.orders.show', $order)">View</x-admin.button></td>
                </tr>
            @empty
                <tr><td colspan="10"><x-admin.empty-state title="No orders found." description="Orders matching the current filters will appear here." icon="bi-handbag-fill" /></td></tr>
            @endforelse
        </x-admin.table>

        <div class="mt-8">{{ $orders->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
