<x-layouts.admin :title="($category->exists ? 'Edit' : 'Add').' category | Cherry Bellemont'">
    <x-admin.section width="2xl">
        <x-admin.page-header eyebrow="Catalogue" :title="$category->exists ? 'Edit category' : 'Add category'" />
        <x-admin.card class="mt-8">
            <form class="space-y-6" method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
                @csrf @if($category->exists) @method('PUT') @endif
                <x-admin.form-input name="name" label="Name" :value="$category->name" required />
                <x-admin.form-input name="slug" label="URL slug" :value="$category->slug" help="Lowercase words separated by hyphens. Leave blank to create it from the name." />
                <x-admin.textarea name="description" label="Collection description" :value="$category->description" class="min-h-28" />
                <x-admin.select name="parent_id" label="Parent category"><option value="">No parent category</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((int) old('parent_id', $category->parent_id) === $parent->id)>{{ $parent->name }}</option>@endforeach</x-admin.select>
                <div class="grid gap-6 sm:grid-cols-2"><x-admin.form-input name="sort_order" label="Display order" type="number" min="0" :value="$category->sort_order ?? 0" /><x-admin.form-input name="image" label="Category image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :help="'Optional. JPG, PNG or WEBP, up to 5 MB'.($category->exists ? '. Leave blank to keep the image.' : '')" /></div>
                @if($category->image_path)<img class="aspect-[3/4] w-40 border border-gold/40 object-cover" src="{{ asset('storage/'.$category->image_path) }}" alt="{{ $category->name }}">@endif
                <x-admin.form-input name="meta_title" label="SEO title" :value="$category->meta_title" />
                <x-admin.textarea name="meta_description" label="SEO description" :value="$category->meta_description" class="min-h-24" />
                <x-admin.checkbox name="is_active" label="Visible on the storefront" :checked="old('is_active', $category->exists ? $category->is_active : true)" />
                <x-admin.button type="submit">Save category</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
