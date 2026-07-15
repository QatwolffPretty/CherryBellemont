<x-layouts.admin title="Reports | Atelier">
    <x-admin.section>
        <x-admin.page-header eyebrow="Atelier intelligence" title="Reports" subtitle="Reports module coming soon." />

        <x-admin.empty-state class="mt-10" title="Reports module coming soon." description="Sales and performance reporting will appear here when the reporting module is ready." icon="bi-graph-up" />

        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['Sales', 'Revenue', 'Orders', 'Products', 'Inventory', 'Most Popular Products', 'Recent Activity'] as $report)
                <x-admin.card>
                    <i class="bi bi-graph-up text-xl text-gold" aria-hidden="true"></i>
                    <h2 class="mt-4 text-2xl">{{ $report }}</h2>
                    <p class="mt-2 text-sm text-cream/60">Available in the upcoming reports module.</p>
                </x-admin.card>
            @endforeach
        </div>
    </x-admin.section>
</x-layouts.admin>
