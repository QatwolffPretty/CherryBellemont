<x-layouts.admin title="Email Logs | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Email operations" title="Email Logs" subtitle="Queued transactional and test-email delivery records. Full email content and payment credentials are not retained here.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.settings.email')" icon="bi bi-send">Send Test Email</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        @if($errors->any())<p class="mt-6 border border-red-300/50 p-4 text-cream">{{ $errors->first() }}</p>@endif

        <x-admin.card class="mt-8" title="Filters">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="GET">
                <x-admin.form-input name="search" label="Search" :value="request('search')" placeholder="Recipient, type, subject, order" />
                <x-admin.form-input name="order_number" label="Order Number" :value="request('order_number')" />
                <x-admin.form-input name="recipient" label="Recipient" :value="request('recipient')" />
                <x-admin.select name="status" label="Status"><option value="">All statuses</option>@foreach(['queued','sent','failed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</x-admin.select>
                <x-admin.form-input name="notification_type" label="Email Type" :value="request('notification_type')" />
                <x-admin.form-input name="from" type="date" label="From" :value="request('from')" />
                <x-admin.form-input name="to" type="date" label="To" :value="request('to')" />
                <div class="flex items-end gap-3"><x-admin.button type="submit" icon="bi bi-funnel">Filter</x-admin.button><x-admin.button variant="outline" :href="route('admin.email-logs.index')">Clear</x-admin.button></div>
            </form>
        </x-admin.card>

        <x-admin.card class="mt-8" title="Delivery history">
            <x-admin.table class="mt-5">
                <x-slot:head><tr><th>Date</th><th>Recipient</th><th>Email Type</th><th>Order</th><th>Subject</th><th>Status</th><th>Sent / Failed</th><th>Attempts</th><th class="text-right">Actions</th></tr></x-slot:head>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y H:i') ?: '—' }}</td>
                        <td>{{ $log->recipient }}</td>
                        <td>{{ str($log->notification_type)->replace('_', ' ')->title() }}@if($log->is_manual_resend)<br><small class="text-gold">Manual resend</small>@endif</td>
                        <td>@if($log->order)<a class="admin-link" href="{{ route('admin.orders.show', $log->order) }}">{{ $log->order->order_number ?? $log->order->number }}</a>@elseif($log->returnRequest){{ $log->returnRequest->return_number }}@else — @endif</td>
                        <td>{{ $log->subject ?: 'Queued email' }}</td>
                        <td><x-admin.badge :status="$log->status" /></td>
                        <td>{{ $log->sent_at?->format('d M Y H:i') ?: ($log->failed_at?->format('d M Y H:i') ?: '—') }}</td>
                        <td>{{ $log->attempts }}</td>
                        <td class="text-right"><x-admin.button variant="outline" :href="route('admin.email-logs.show', $log)">View</x-admin.button></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-admin.empty-state title="No email logs found." description="Queued transactional and Mailpit test emails will appear here." icon="bi-envelope-paper" /></td></tr>
                @endforelse
            </x-admin.table>
            <div class="mt-6">{{ $logs->links() }}</div>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
