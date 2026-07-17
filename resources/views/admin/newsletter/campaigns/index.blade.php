<x-layouts.admin title="Newsletter Campaigns | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client relationships" title="Newsletter campaigns" subtitle="Prepare, schedule, and monitor branded communications for active subscribers.">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.newsletter.index')" icon="bi-people">Subscribers</x-admin.button>
                <x-admin.button :href="route('admin.newsletter.campaigns.create')" icon="bi-plus-lg">Create campaign</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif
        @error('campaign')<p class="mt-6 border border-red-300/50 p-4 text-red-100">{{ $message }}</p>@enderror

        <form class="mt-8 grid gap-3 md:grid-cols-[1fr_15rem_auto]" method="GET" action="{{ route('admin.newsletter.campaigns.index') }}">
            <x-admin.form-input name="search" :value="request('search')" placeholder="Campaign name or subject" aria-label="Search campaigns" class="mt-0" />
            <x-admin.select name="status" aria-label="Filter campaign status" class="mt-0">
                <option value="">All campaign statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.button type="submit" variant="outline" icon="bi-funnel">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-8">
            <x-slot:head><tr><th>Campaign</th><th>Status</th><th>Audience</th><th>Schedule</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Created</th><th></th></tr></x-slot:head>
            @forelse($campaigns as $campaign)
                <tr>
                    <td><strong>{{ $campaign->name }}</strong><p class="mt-1 text-sm text-cream/60">{{ $campaign->subject }}</p></td>
                    <td><x-admin.badge :status="$campaign->status" /></td>
                    <td>{{ match($campaign->audience_type) { 'subscribed_last_30_days' => 'Last 30 days', 'subscribed_last_90_days' => 'Last 90 days', default => 'All active' } }}</td>
                    <td>{{ $campaign->scheduled_at?->format('d M Y, H:i') ?? '—' }}</td>
                    <td>{{ $campaign->recipient_count }}</td>
                    <td>{{ $campaign->sent_count }}</td>
                    <td>{{ $campaign->failed_count }}</td>
                    <td>{{ $campaign->created_at?->format('d M Y') }}</td>
                    <td class="text-right"><x-admin.button variant="outline" :href="route('admin.newsletter.campaigns.show', $campaign)">View</x-admin.button></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-admin.empty-state title="No campaigns yet." description="Create a draft when your next Cherry Bellemont announcement is ready." icon="bi-envelope-paper" /></td></tr>
            @endforelse
        </x-admin.table>

        <div class="mt-8">{{ $campaigns->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
