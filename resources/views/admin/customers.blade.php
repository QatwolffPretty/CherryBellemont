<x-layouts.admin title="Customers | Atelier">
    <x-admin.section>
        <x-admin.page-header eyebrow="Atelier" title="Customers" subtitle="A considered customer relationship area is being prepared for the atelier." />

        <x-admin.empty-state class="mt-10" title="Coming Soon" description="Customer records will appear here when the relationship module is ready." icon="bi-people" />

        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['Customer List', 'Customer Profiles', 'Order History', 'Lifetime Value', 'Customer Notes'] as $module)
                <x-admin.card>{{ $module }}</x-admin.card>
            @endforeach
        </div>
    </x-admin.section>
</x-layouts.admin>
