<x-layouts.admin title="Categories | Cherry Bellemont">
    <x-admin.section>
        <x-admin.page-header eyebrow="Catalogue" title="Categories" subtitle="Organise the collection without changing existing product stock or order history.">
            <x-slot:actions><x-admin.button :href="route('admin.categories.create')" icon="bi-folder-plus">Add category</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif
        <x-admin.card class="mt-8">
            <form class="flex flex-wrap items-end gap-4" method="GET">
                <x-admin.select class="mt-0 w-52" name="status" label="Status"><option value="">All categories</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></x-admin.select>
                <x-admin.button type="submit" icon="bi-funnel">Filter</x-admin.button>
            </form>
        </x-admin.card>

        <x-admin.table class="mt-8">
            <x-slot:head><tr><th>Category</th><th>Parent</th><th>Products</th><th>Order</th><th>Status</th><th></th></tr></x-slot:head>
            @forelse($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong><br><small class="text-cream/60">/{{ $category->slug }}</small></td>
                    <td>{{ $category->parent?->name ?: '—' }}</td><td>{{ $category->products_count }}</td><td>{{ $category->sort_order }}</td>
                    <td><x-admin.badge :status="$category->is_active ? 'active' : 'inactive'" :label="$category->is_active ? 'Active' : 'Inactive'" /></td>
                    <td class="text-right"><x-admin.button variant="outline" :href="route('admin.categories.edit', $category)">Edit</x-admin.button><form class="ml-2 inline" method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Remove or deactivate this category?')">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit">Delete</x-admin.button></form></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-admin.empty-state title="No categories yet." description="Add the first collection category to organise your products." icon="bi-folder" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $categories->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
