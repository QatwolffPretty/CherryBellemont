<x-layouts.admin title="Back-in-stock requests | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Catalogue" title="Back-in-stock requests" subtitle="Monitor customers waiting for out-of-stock Cherry Bellemont pieces.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.products.index')" icon="bi-box-seam">Products</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-admin.stats-card label="Waiting Requests" :value="$stats['waiting']" accent />
            <x-admin.stats-card label="Products With Waiting Customers" :value="$stats['products_with_waiting']" />
            <x-admin.stats-card label="Most Requested Product" :value="$stats['most_requested']?->name ?: 'No waiting demand'" :subtitle="$stats['most_requested'] ? $stats['most_requested_count'].' waiting request(s)' : null" />
        </div>

        <form class="mt-8 grid gap-3 md:grid-cols-[16rem_1fr_auto]" method="GET" action="{{ route('admin.product-stock-notifications.index') }}">
            <x-admin.select name="status" aria-label="Filter request status" class="mt-0"><option value="">All statuses</option>@foreach(['waiting' => 'Waiting', 'notified' => 'Notified', 'cancelled' => 'Cancelled'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</x-admin.select>
            <x-admin.select name="product_id" aria-label="Filter product" class="mt-0"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>@endforeach</x-admin.select>
            <x-admin.button type="submit" variant="outline" icon="bi-funnel">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-8">
            <x-slot:head><tr><th>Product</th><th>Customer</th><th>Status</th><th>Requested</th><th>Notified</th></tr></x-slot:head>
            @forelse($notifications as $notification)
                <tr>
                    <td>{{ $notification->product?->name ?: 'Unavailable product' }}<p class="mt-1 text-sm text-cream/60">{{ $notification->product?->stock ?? '—' }} in stock</p></td>
                    <td>{{ $notification->name ?: 'Guest' }}<p class="mt-1 text-sm text-cream/60">{{ $notification->email }}</p></td>
                    <td><x-admin.badge :status="$notification->status" /></td>
                    <td>{{ $notification->requested_at?->format('d M Y, H:i') }}</td>
                    <td>{{ $notification->notified_at?->format('d M Y, H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty-state title="No back-in-stock requests." description="Requests appear here when a customer asks to be notified about an unavailable piece." icon="bi-bell" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $notifications->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
