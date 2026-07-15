<x-layouts.admin title="Delivery methods">
    <x-admin.section>
        <x-admin.page-header title="Delivery methods">
            <x-slot:actions><x-admin.button :href="route('admin.delivery-methods.create')">Add method</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <x-admin.table class="mt-8">
            <x-slot:head>
                <tr><th>Method</th><th>Code</th><th>Additional fee</th><th>Estimate</th><th>Pickup</th><th>Status</th><th></th></tr>
            </x-slot:head>
            @forelse($methods as $method)
                <tr>
                    <td>{{ $method->name }}</td>
                    <td>{{ $method->code }}</td>
                    <td>RM {{ number_format($method->additional_fee, 2) }}</td>
                    <td>{{ $method->estimated_days ?: '—' }}</td>
                    <td><x-admin.badge :status="$method->is_pickup ? 'active' : 'pending'" :label="$method->is_pickup ? 'Yes' : 'No'" /></td>
                    <td><x-admin.badge :status="$method->is_active ? 'active' : 'archived'" :label="$method->is_active ? 'Active' : 'Inactive'" /></td>
                    <td><x-admin.button variant="outline" :href="route('admin.delivery-methods.edit', $method)">Edit</x-admin.button></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-admin.empty-state title="No delivery methods yet." description="Add a delivery method to make it available at checkout." icon="bi-truck" /></td></tr>
            @endforelse
        </x-admin.table>
    </x-admin.section>
</x-layouts.admin>
