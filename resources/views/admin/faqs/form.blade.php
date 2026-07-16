<x-layouts.admin :title="($faq->exists ? 'Edit' : 'Add').' FAQ | Cherry Bellemont'">
    <x-admin.section width="2xl">
        <x-admin.page-header eyebrow="Client care" :title="$faq->exists ? 'Edit FAQ' : 'Add FAQ'" />

        <x-admin.card class="mt-8">
            <form class="space-y-6" method="POST" action="{{ $faq->exists ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}">
                @csrf
                @if($faq->exists)@method('PUT')@endif
                <x-admin.form-input name="question" label="Question" :value="$faq->question" required />
                <x-admin.textarea name="answer" label="Answer" :value="$faq->answer" help="Basic paragraph, list, bold and italic tags are allowed. Unsafe HTML and attributes are removed." required />
                <x-admin.form-input name="category" label="Category" :value="$faq->category" placeholder="Orders, Payments, Shipping…" />
                <x-admin.form-input name="sort_order" label="Sort order" type="number" min="0" :value="$faq->exists ? $faq->sort_order : 0" required />
                <x-admin.checkbox name="is_active" label="Show this FAQ on the storefront" :checked="old('is_active', $faq->exists ? $faq->is_active : true)" />
                <div class="flex flex-wrap gap-3"><x-admin.button type="submit">Save FAQ</x-admin.button><x-admin.button variant="outline" :href="route('admin.faqs.index')">Cancel</x-admin.button></div>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
