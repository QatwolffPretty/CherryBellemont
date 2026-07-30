<x-layouts.admin :title="$title.' | Cherry Bellemont'">
    <x-admin.section>
        <x-admin.page-header eyebrow="Catalogue" :title="$title" subtitle="Reusable product availability options. Deactivating an assigned option keeps historical assignments intact.">
            <x-slot:actions><x-admin.button :href="route($createRoute)" icon="bi-plus-lg">Add {{ str($type)->replace('_', ' ') }}</x-admin.button></x-slot:actions>
        </x-admin.page-header>
        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif
        <x-admin.card class="mt-8"><form class="flex flex-wrap items-end gap-4" method="GET"><x-admin.select class="mt-0 w-52" name="status" label="Status"><option value="">All options</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></x-admin.select><x-admin.button type="submit" icon="bi-funnel">Filter</x-admin.button></form></x-admin.card>
        <x-admin.table class="mt-8"><x-slot:head><tr><th>{{ $type === 'tag' ? 'Collection tag' : ucfirst($type) }}</th>@if($type === 'colour')<th>Swatch</th>@endif<th>Products</th><th>Order</th><th>Status</th><th></th></tr></x-slot:head>
            @forelse($items as $item)
                <tr><td><strong>{{ $item->name }}</strong><br><small class="text-cream/60">{{ $type === 'size' ? $item->code : $item->slug }}</small></td>@if($type === 'colour')<td>@if($item->hex_code)<span class="inline-block h-5 w-8 border border-gold/40 align-middle" style="background-color: {{ $item->hex_code }}" aria-label="{{ $item->hex_code }}"></span> <small>{{ $item->hex_code }}</small>@else — @endif</td>@endif<td>{{ $item->products_count }}</td><td>{{ $item->sort_order }}</td><td><x-admin.badge :status="$item->is_active ? 'active' : 'inactive'" :label="$item->is_active ? 'Active' : 'Inactive'" /></td><td class="text-right"><x-admin.button variant="outline" :href="route($editRoute, $item)">Edit</x-admin.button><form class="ml-2 inline" method="POST" action="{{ route($destroyRoute, $item) }}" onsubmit="return confirm('Remove or deactivate this option?')">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit">Delete</x-admin.button></form></td></tr>
            @empty
                <tr><td colspan="{{ $type === 'colour' ? 6 : 5 }}"><x-admin.empty-state title="No options yet." description="Add a reusable catalogue option to assign it to products." icon="bi-tags" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $items->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
