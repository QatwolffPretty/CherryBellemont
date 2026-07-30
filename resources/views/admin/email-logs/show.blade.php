<x-layouts.admin title="Email Log | Cherry Bellemont">
    <x-admin.section width="5xl">
        <x-admin.page-header eyebrow="Email operations" title="Email Delivery Record" subtitle="The log retains delivery metadata only, not the full email body.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.email-logs.index')" icon="bi bi-arrow-left">Email Logs</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        @if($errors->any())<p class="mt-6 border border-red-300/50 p-4 text-cream">{{ $errors->first() }}</p>@endif

        <x-admin.card class="mt-8" title="Delivery details">
            <dl class="mt-5 grid gap-5 md:grid-cols-2">
                <div><dt class="text-sm text-cream/60">Recipient</dt><dd>{{ $emailLog->recipient }}</dd></div>
                <div><dt class="text-sm text-cream/60">Email Type</dt><dd>{{ str($emailLog->notification_type)->replace('_', ' ')->title() }}</dd></div>
                <div><dt class="text-sm text-cream/60">Subject</dt><dd>{{ $emailLog->subject ?: 'Queued email' }}</dd></div>
                <div><dt class="text-sm text-cream/60">Status</dt><dd><x-admin.badge :status="$emailLog->status" /></dd></div>
                <div><dt class="text-sm text-cream/60">Queued</dt><dd>{{ $emailLog->queued_at?->format('d M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-sm text-cream/60">Sent</dt><dd>{{ $emailLog->sent_at?->format('d M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-sm text-cream/60">Failed</dt><dd>{{ $emailLog->failed_at?->format('d M Y H:i') ?: '—' }}</dd></div>
                <div><dt class="text-sm text-cream/60">Attempts</dt><dd>{{ $emailLog->attempts }}</dd></div>
                <div><dt class="text-sm text-cream/60">Order</dt><dd>@if($emailLog->order)<a class="admin-link" href="{{ route('admin.orders.show', $emailLog->order) }}">{{ $emailLog->order->order_number ?? $emailLog->order->number }}</a>@else — @endif</dd></div>
                <div><dt class="text-sm text-cream/60">Manual Resend</dt><dd>{{ $emailLog->is_manual_resend ? 'Yes'.($emailLog->resentBy ? ' · '.$emailLog->resentBy->name : '') : 'No' }}</dd></div>
            </dl>
            @if($emailLog->error_message)<div class="mt-6 border-l-2 border-gold px-4 py-3"><p class="text-sm text-cream/60">Safe error summary</p><p class="mt-1">{{ $emailLog->error_message }}</p></div>@endif
            @if($emailLog->metadata)<div class="mt-6 border-t border-cream/15 pt-5"><p class="text-sm text-cream/60">Event metadata</p><dl class="mt-3 grid gap-3 md:grid-cols-2">@foreach($emailLog->metadata as $key => $value)<div><dt class="capitalize text-cream/60">{{ str($key)->replace('_', ' ') }}</dt><dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? '—') }}</dd></div>@endforeach</dl></div>@endif
            @if($emailLog->order || ($emailLog->returnRequest && in_array($emailLog->notification_type, ['refund_processing', 'refund_succeeded', 'refund_failed'], true)))
                <form class="mt-7" method="POST" action="{{ route('admin.email-logs.resend', $emailLog) }}" onsubmit="return confirm('Queue a manual resend of this customer email?');">@csrf <x-admin.button type="submit" icon="bi bi-arrow-repeat">Resend Email</x-admin.button></form>
            @endif
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
