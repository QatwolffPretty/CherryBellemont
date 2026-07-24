<x-layouts.admin title="Returns & Refunds | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Aftercare" title="Returns & Refunds" subtitle="Review customer requests, inspect returned items, and record only verified refunds." />

        <form class="mt-6 grid gap-3 md:grid-cols-4" method="GET">
            <x-admin.form-input name="search" :value="request('search')" placeholder="Return, order, customer, product" aria-label="Search returns" class="mt-0" />
            <x-admin.select name="status" aria-label="Filter return status" class="mt-0">
                <option value="">All statuses</option>
                @foreach(['requested','under_review','approved','awaiting_return','item_received','inspecting','resolution_pending','inspection_failed','completed','rejected','closed'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.select name="provider" aria-label="Filter payment provider" class="mt-0">
                <option value="">All payment providers</option>
                <option value="duitnow" @selected(request('provider') === 'duitnow')>DuitNow</option>
                <option value="stripe" @selected(request('provider') === 'stripe')>Stripe</option>
            </x-admin.select>
            <x-admin.button type="submit">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-8">
            <x-slot:head><tr><th>Return</th><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th>Payment</th><th>Requested</th><th></th></tr></x-slot:head>
            @forelse($returns as $returnRequest)
                <tr>
                    <td>{{ $returnRequest->return_number }}</td>
                    <td>{{ $returnRequest->order?->order_number ?? '—' }}</td>
                    <td>{{ $returnRequest->customer_name }}<br><small>{{ $returnRequest->customer_email }}</small></td>
                    <td class="capitalize">{{ $returnRequest->request_type }}</td>
                    <td><x-admin.badge :status="$returnRequest->status" /></td>
                    <td><x-admin.badge :status="$returnRequest->order?->payment_provider ?? $returnRequest->order?->payment_method" :label="$returnRequest->order?->payment_provider ?? $returnRequest->order?->payment_method ?? '—'" /></td>
                    <td>{{ $returnRequest->requested_at?->format('d M Y H:i') }}</td>
                    <td><x-admin.button variant="outline" :href="route('admin.returns.show', $returnRequest)">View</x-admin.button></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-admin.empty-state title="No return requests found." description="Customer aftercare requests will appear here after they are submitted from an eligible delivered order." icon="bi-arrow-repeat" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $returns->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
