<x-layouts.admin title="FAQ | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client care" title="FAQ" subtitle="Manage the editable FAQ guidance shown on the storefront.">
            <x-slot:actions><x-admin.button :href="route('admin.faqs.create')" icon="bi-plus-lg">Add FAQ</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        <form class="mt-8 grid gap-3 md:grid-cols-[1fr_14rem_auto]" method="GET">
            <x-admin.form-input name="search" :value="request('search')" placeholder="Question, answer, category" aria-label="Search FAQs" class="mt-0" />
            <x-admin.select name="active" aria-label="Filter FAQ status" class="mt-0"><option value="">All statuses</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Inactive</option></x-admin.select>
            <x-admin.button type="submit">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-6">
            <x-slot:head><tr><th>Question</th><th>Category</th><th>Order</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></x-slot:head>
            @forelse($faqs as $faq)
                <tr>
                    <td><p class="max-w-xl">{{ $faq->question }}</p></td>
                    <td>{{ $faq->category ?: 'General' }}</td>
                    <td>{{ $faq->sort_order }}</td>
                    <td><x-admin.badge :status="$faq->is_active ? 'active' : 'inactive'" /></td>
                    <td><div class="flex flex-wrap justify-end gap-2"><x-admin.button variant="outline" :href="route('admin.faqs.edit', $faq)">Edit</x-admin.button><form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" onsubmit="return confirm('Delete this FAQ?')">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger">Delete</x-admin.button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty-state title="No FAQs found" description="Run the sample FAQ seeder or create your first question." icon="bi-patch-question" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $faqs->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
