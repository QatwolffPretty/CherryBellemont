<x-layouts.admin title="Settings Audit History">
    <div class="space-y-8 px-6 py-8 lg:px-10">
        <x-admin.page-header title="Settings Audit History" eyebrow="Configuration"><x-slot:actions><a class="admin-button admin-button-secondary" href="{{ route('admin.settings.index') }}">Back to Settings</a></x-slot:actions></x-admin.page-header>
        <x-admin.card>
            <form method="GET" class="grid gap-4 md:grid-cols-4">
                <input class="admin-field" name="group" placeholder="Group" value="{{ request('group') }}">
                <input class="admin-field" name="key" placeholder="Setting key" value="{{ request('key') }}">
                <input class="admin-field" type="date" name="from" value="{{ request('from') }}">
                <div class="flex gap-3"><input class="admin-field" type="date" name="to" value="{{ request('to') }}"><button class="admin-button admin-button-primary" type="submit">Filter</button></div>
            </form>
        </x-admin.card>
        <x-admin.table>
            <x-slot:head><tr><th>Setting</th><th>Changed By</th><th>Previous</th><th>New</th><th>Date</th></tr></x-slot:head>
            @forelse($logs as $log)<tr><td>{{ $log->group }}.{{ $log->key }}</td><td>{{ $log->changer?->name ?? 'System' }}</td><td>{{ \Illuminate\Support\Str::limit((string) $log->old_value, 100) ?: '—' }}</td><td>{{ \Illuminate\Support\Str::limit((string) $log->new_value, 100) ?: '—' }}</td><td>{{ $log->created_at?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="py-12 text-center text-cream/60">No setting changes have been recorded yet.</td></tr>@endforelse
        </x-admin.table>
        {{ $logs->links() }}
    </div>
</x-layouts.admin>
