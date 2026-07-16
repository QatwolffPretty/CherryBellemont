<x-layouts.admin title="Newsletter | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client relationships" title="Newsletter subscribers" subtitle="Manage subscribers and export the active Cherry Bellemont List.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.newsletter.export')" icon="bi-download">Export active CSV</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stats-card label="Active Subscribers" :value="$stats['active']" />
            <x-admin.stats-card label="New This Month" :value="$stats['new_this_month']" accent />
            <x-admin.stats-card label="Unsubscribed" :value="$stats['unsubscribed']" />
            <x-admin.stats-card label="Total Subscribers" :value="$stats['total']" />
        </div>

        <form class="mt-8 grid gap-3 md:grid-cols-[1fr_14rem_auto]" method="GET">
            <x-admin.form-input name="search" :value="request('search')" placeholder="Name or email" aria-label="Search subscribers" class="mt-0" />
            <x-admin.select name="status" aria-label="Filter subscriber status" class="mt-0"><option value="">All statuses</option><option value="subscribed" @selected(request('status') === 'subscribed')>Subscribed</option><option value="unsubscribed" @selected(request('status') === 'unsubscribed')>Unsubscribed</option><option value="pending" @selected(request('status') === 'pending')>Pending</option></x-admin.select>
            <x-admin.button type="submit">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-6">
            <x-slot:head><tr><th>Subscriber</th><th>Status</th><th>Subscribed</th><th>Source</th><th><span class="sr-only">Actions</span></th></tr></x-slot:head>
            @forelse($subscribers as $subscriber)
                <tr>
                    <td><p>{{ $subscriber->name ?: '—' }}</p><p class="mt-1 text-sm text-cream/60">{{ $subscriber->email }}</p></td>
                    <td><x-admin.badge :status="$subscriber->status" /></td>
                    <td>{{ $subscriber->subscribed_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $subscriber->source ?: '—' }}</td>
                    <td>
                        <div class="flex flex-wrap justify-end gap-2">
                            @if($subscriber->status === 'subscribed')
                                <form method="POST" action="{{ route('admin.newsletter.unsubscribe', $subscriber) }}">@csrf @method('PATCH')<x-admin.button type="submit" variant="warning">Unsubscribe</x-admin.button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.newsletter.destroy', $subscriber) }}" onsubmit="return confirm('Delete this subscriber record?')">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger">Delete</x-admin.button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty-state title="No subscribers found" description="New footer subscriptions will appear here." icon="bi-envelope-paper" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $subscribers->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
