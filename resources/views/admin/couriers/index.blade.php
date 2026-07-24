<x-layouts.admin title="Couriers | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Shipment management" title="Couriers" subtitle="Manage editable courier examples and tracking-link templates. No courier API is connected in manual mode.">
            <x-slot:actions><x-admin.button :href="route('admin.couriers.create')" icon="bi-truck">Add Courier</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        <x-admin.card class="mt-8">
            <form class="grid gap-4 md:grid-cols-[1fr_12rem_auto]" method="GET">
                <x-admin.form-input class="mt-0" name="search" label="Search" :value="request('search')" />
                <x-admin.select class="mt-0" name="status" label="Status"><option value="">All couriers</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></x-admin.select>
                <div class="flex items-end"><x-admin.button class="w-full" type="submit" icon="bi-funnel">Filter</x-admin.button></div>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-8" title="Courier companies">
            <x-admin.table class="mt-6">
                <x-slot:head><tr><th>Courier</th><th>Code</th><th>Tracking</th><th>Status</th><th>Order</th><th></th></tr></x-slot:head>
                @forelse($couriers as $courier)
                    <tr>
                        <td><div class="flex items-center gap-3">@if($courier->logo_path)<img class="h-9 w-9 border border-cream/20 object-contain" src="{{ asset('storage/'.$courier->logo_path) }}" alt="">@endif<span>{{ $courier->name }}</span></div></td>
                        <td>{{ $courier->code }}</td>
                        <td>{{ $courier->tracking_url_template ? 'Template configured' : 'Manual tracking' }}</td>
                        <td><x-admin.badge :status="$courier->is_active ? 'active' : 'archived'" :label="$courier->is_active ? 'Active' : 'Inactive'" /></td>
                        <td>{{ $courier->sort_order }}</td>
                        <td><x-admin.button variant="outline" :href="route('admin.couriers.edit', $courier)">Edit</x-admin.button></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty-state icon="bi-truck" title="No couriers configured." description="Add a courier before creating shipments." /></td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-6">{{ $couriers->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
