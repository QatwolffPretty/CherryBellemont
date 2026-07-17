<x-layouts.admin :title="$campaign->name.' | Newsletter Campaigns'">
    @php
        $pendingCount = (int) ($counts['pending'] ?? 0) + (int) ($counts['queued'] ?? 0);
        $completeCount = (int) ($counts['sent'] ?? 0) + (int) ($counts['failed'] ?? 0) + (int) ($counts['skipped'] ?? 0);
        $progress = $campaign->recipient_count > 0 ? min(100, (int) round(($completeCount / $campaign->recipient_count) * 100)) : 0;
    @endphp
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client relationships" :title="$campaign->name" :subtitle="$campaign->subject">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.newsletter.campaigns.index')">All campaigns</x-admin.button>
                <x-admin.button variant="outline" :href="route('admin.newsletter.campaigns.preview', $campaign)" icon="bi-eye">Preview</x-admin.button>
                @if(in_array($campaign->status, ['draft', 'scheduled']))<x-admin.button variant="outline" :href="route('admin.newsletter.campaigns.edit', $campaign)" icon="bi-pencil">Edit</x-admin.button>@endif
            </x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif
        @error('campaign')<p class="mt-6 border border-red-300/50 p-4 text-red-100">{{ $message }}</p>@enderror

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stats-card label="Status" :value="str($campaign->status)->replace('_', ' ')->title()" :href="route('admin.newsletter.campaigns.index', ['status' => $campaign->status])" />
            <x-admin.stats-card label="Recipients" :value="$campaign->recipient_count" />
            <x-admin.stats-card label="Sent" :value="$campaign->sent_count" accent />
            <x-admin.stats-card label="Failed" :value="$campaign->failed_count" />
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-3">
            <x-admin.card title="Campaign details" class="xl:col-span-2">
                <dl class="mt-6 grid gap-5 md:grid-cols-2">
                    <div><dt class="text-sm text-cream/60">Audience</dt><dd class="mt-1">{{ match($campaign->audience_type) { 'subscribed_last_30_days' => 'Subscribers from the last 30 days', 'subscribed_last_90_days' => 'Subscribers from the last 90 days', default => 'All active subscribers' } }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Created</dt><dd class="mt-1">{{ $campaign->created_at?->format('d M Y, H:i') }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Scheduled</dt><dd class="mt-1">{{ $campaign->scheduled_at?->format('d M Y, H:i') ?? 'Not scheduled' }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Started</dt><dd class="mt-1">{{ $campaign->sending_started_at?->format('d M Y, H:i') ?? 'Not started' }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Completed</dt><dd class="mt-1">{{ $campaign->sent_at?->format('d M Y, H:i') ?? 'Not completed' }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Open and click tracking</dt><dd class="mt-1">Not yet tracked</dd></div>
                </dl>
                @if($campaign->preview_text)<p class="mt-6 border-l-2 border-gold pl-4 text-cream/70">{{ $campaign->preview_text }}</p>@endif
            </x-admin.card>

            <x-admin.card title="Delivery progress">
                <p class="mt-6 text-4xl text-gold">{{ $progress }}%</p>
                <div class="mt-4 h-3 border border-gold/50 bg-wine-deep" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><div class="h-full bg-gold" style="width: {{ $progress }}%"></div></div>
                <dl class="mt-6 grid gap-3 text-sm"><div class="flex justify-between"><dt>Pending or queued</dt><dd>{{ $pendingCount }}</dd></div><div class="flex justify-between"><dt>Sent</dt><dd>{{ $counts['sent'] ?? 0 }}</dd></div><div class="flex justify-between"><dt>Failed</dt><dd>{{ $counts['failed'] ?? 0 }}</dd></div><div class="flex justify-between"><dt>Skipped</dt><dd>{{ $counts['skipped'] ?? 0 }}</dd></div></dl>
                <x-admin.button variant="outline" class="mt-6 w-full" :href="route('admin.newsletter.campaigns.deliveries', $campaign)">View delivery report</x-admin.button>
            </x-admin.card>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Send a test copy">
                <p class="mt-3 text-sm text-cream/60">A test uses the final subscriber email design but never creates a campaign delivery record.</p>
                <form class="mt-6 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('admin.newsletter.campaigns.test', $campaign) }}">
                    @csrf
                    <x-admin.form-input name="email" type="email" label="Recipient Email" required />
                    <x-admin.form-input name="name" label="Recipient Name" />
                    <div class="md:col-span-2"><x-admin.button type="submit" variant="outline" icon="bi-send">Queue test email</x-admin.button></div>
                </form>
            </x-admin.card>

            @if(in_array($campaign->status, ['draft', 'scheduled']))
                <x-admin.card title="Schedule or send">
                    <form class="grid gap-4 md:grid-cols-[1fr_auto]" method="POST" action="{{ route('admin.newsletter.campaigns.schedule', $campaign) }}">
                        @csrf
                        <x-admin.form-input name="scheduled_at" type="datetime-local" label="Schedule Date and Time" :value="$campaign->scheduled_at?->format('Y-m-d\TH:i')" required />
                        <div class="flex items-end"><x-admin.button type="submit" variant="outline" icon="bi-calendar-event">Schedule</x-admin.button></div>
                    </form>
                    <form class="mt-8 border-t border-cream/15 pt-6" method="POST" action="{{ route('admin.newsletter.campaigns.send', $campaign) }}" onsubmit="return confirm('Queue this campaign for all eligible active subscribers?')">
                        @csrf
                        <label class="flex gap-3 text-sm"><input type="checkbox" name="confirm_send" value="1" required> <span>I confirm this campaign should be sent to its selected active audience.</span></label>
                        @error('confirm_send')<x-admin.validation-error :message="$message" />@enderror
                        <x-admin.button class="mt-5" type="submit" icon="bi-send-check">Send now</x-admin.button>
                    </form>
                </x-admin.card>
            @else
                <x-admin.card title="Campaign actions">
                    <p class="mt-3 text-sm text-cream/60">Sent and archived campaigns retain their delivery history. Make a clean draft when you need to reuse this message.</p>
                    <form class="mt-6 inline" method="POST" action="{{ route('admin.newsletter.campaigns.duplicate', $campaign) }}">@csrf <x-admin.button type="submit" variant="outline" icon="bi-copy">Duplicate as draft</x-admin.button></form>
                    @if(in_array($campaign->status, ['draft', 'sent', 'failed']))
                        <form class="mt-3 inline" method="POST" action="{{ route('admin.newsletter.campaigns.archive', $campaign) }}" onsubmit="return confirm('Archive this campaign?')">@csrf @method('PATCH') <x-admin.button type="submit" variant="warning" icon="bi-archive">Archive</x-admin.button></form>
                    @endif
                </x-admin.card>
            @endif
        </div>
    </x-admin.section>
</x-layouts.admin>
