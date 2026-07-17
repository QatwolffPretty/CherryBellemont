<x-layouts.admin :title="$campaign->name.' delivery report | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client relationships" title="Campaign delivery report" :subtitle="$campaign->name">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.newsletter.campaigns.show', $campaign)">Back to campaign</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mt-8">
            <p class="text-sm text-cream/60">Open and click information is intentionally not claimed until provider tracking is configured.</p>
            <x-admin.table class="mt-6">
                <x-slot:head><tr><th>Subscriber</th><th>Status</th><th>Queued</th><th>Sent</th><th>Failure Summary</th><th>Engagement</th></tr></x-slot:head>
                @forelse($deliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->name ?: 'Subscriber' }}<p class="mt-1 text-sm text-cream/60">{{ $delivery->email }}</p></td>
                        <td><x-admin.badge :status="$delivery->status" /></td>
                        <td>{{ $delivery->queued_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td>{{ $delivery->sent_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td>{{ $delivery->failure_reason ?: '—' }}</td>
                        <td>{{ $delivery->opened_at || $delivery->clicked_at ? 'Available' : 'Not yet tracked' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty-state title="No delivery records yet." description="Delivery records appear after a campaign is sent." icon="bi-envelope-paper" /></td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-8">{{ $deliveries->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
