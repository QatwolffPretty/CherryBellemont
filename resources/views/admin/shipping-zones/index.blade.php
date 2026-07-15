<x-layouts.admin title="Shipping zones">
    <x-admin.section>
        <x-admin.page-header title="Shipping zones">
            <x-slot:actions><x-admin.button :href="route('admin.shipping-zones.create')">Add zone</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <x-admin.table class="mt-8">
            <x-slot:head>
                <tr><th>Zone</th><th>State</th><th>City / area</th><th>Postcode range</th><th>Base fee</th><th>Status</th><th></th></tr>
            </x-slot:head>
            @forelse($zones as $zone)
                <tr>
                    <td>{{ $zone->name }}</td>
                    <td>{{ $zone->state }}</td>
                    <td>{{ $zone->city_or_area ?: '—' }}</td>
                    <td>{{ $zone->postcode_from ?: '—' }} @if($zone->postcode_to)– {{ $zone->postcode_to }}@endif</td>
                    <td>RM {{ number_format($zone->base_fee, 2) }}</td>
                    <td><x-admin.badge :status="$zone->is_active ? 'active' : 'archived'" :label="$zone->is_active ? 'Active' : 'Inactive'" /></td>
                    <td><x-admin.button variant="outline" :href="route('admin.shipping-zones.edit', $zone)">Edit</x-admin.button></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-admin.empty-state title="No shipping zones yet." description="Add a delivery area to begin calculating shipping fees." icon="bi-geo-alt" /></td></tr>
            @endforelse
        </x-admin.table>
    </x-admin.section>
</x-layouts.admin>
