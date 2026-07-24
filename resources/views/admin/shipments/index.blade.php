<x-layouts.admin title="Shipments | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Fulfilment" title="Shipments" subtitle="Manual courier tracking, private labels, and a complete shipment timeline." />
        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        <x-admin.card class="mt-8">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="GET">
                <x-admin.form-input class="mt-0" name="search" label="Search" :value="request('search')" />
                <x-admin.select class="mt-0" name="status" label="Shipment status"><option value="">All shipment statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</x-admin.select>
                <x-admin.select class="mt-0" name="courier_id" label="Courier"><option value="">All couriers</option>@foreach($couriers as $courier)<option value="{{ $courier->id }}" @selected((string) request('courier_id') === (string) $courier->id)>{{ $courier->name }}</option>@endforeach</x-admin.select>
                <div class="flex items-end"><x-admin.button class="w-full" type="submit" icon="bi-funnel">Filter</x-admin.button></div>
            </form>
        </x-admin.card>
        <x-admin.card class="mt-8" title="Outbound shipments">
            <x-admin.table class="mt-6"><x-slot:head><tr><th>Shipment</th><th>Order / Customer</th><th>Courier</th><th>Tracking</th><th>Shipment</th><th>Fulfilment</th><th>Shipped</th><th>Estimated delivery</th><th></th></tr></x-slot:head>
                @forelse($shipments as $shipment)
                    <tr><td>{{ $shipment->shipment_number }}</td><td>{{ $shipment->order?->order_number ?: $shipment->order?->number }}<br><small>{{ $shipment->order?->customer_name ?: $shipment->order?->customer_email }}</small></td><td>{{ $shipment->courier_name_snapshot ?: '—' }}</td><td>{{ $shipment->tracking_number ?: '—' }}</td><td><x-admin.badge :status="$shipment->shipment_status" /></td><td><x-admin.badge :status="$shipment->order?->order_status" /></td><td>{{ $shipment->shipped_at?->format('d M Y H:i') ?: '—' }}</td><td>{{ $shipment->estimated_delivery_at?->format('d M Y') ?: '—' }}</td><td><x-admin.button variant="outline" :href="route('admin.shipments.show', $shipment)">View</x-admin.button></td></tr>
                @empty<tr><td colspan="9"><x-admin.empty-state icon="bi-truck" title="No shipments yet." description="Create a shipment from a paid, packed order." /></td></tr>@endforelse
            </x-admin.table><div class="mt-6">{{ $shipments->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
