<x-layouts.admin :title="($product->exists ? 'Edit' : 'Add').' piece | Cherry Bellemont'">
    <x-admin.section width="2xl">
        <x-admin.page-header :title="$product->exists ? 'Edit piece' : 'Add a piece'" />

        <x-admin.card class="mt-10">
            <form class="space-y-6" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
                @csrf
                @if($product->exists) @method('PUT') @endif

                <x-admin.form-input name="name" label="Name" :value="$product->name" required />
                <x-admin.textarea name="description" label="Description" :value="$product->description" class="min-h-32" />
                <x-admin.form-input name="price" label="Price (RM)" type="number" step="0.01" min="0" :value="$product->price" required />
                <x-admin.form-input name="stock" label="Available stock" type="number" min="0" :value="$product->stock ?? 0" required />
                <x-admin.select name="status" label="Status">
                    @foreach(['draft', 'active', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $product->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </x-admin.select>
                <x-admin.form-input id="image" name="image" label="Product image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" :help="'JPG, PNG, or WEBP up to 5 MB'.($product->exists ? '. Leave blank to keep the current image.' : '')" />

                @if($product->image_path)
                    <img class="aspect-[3/4] w-40 border border-gold/40 object-cover" src="{{ asset('storage/' . $product->image_path) }}" alt="Current image for {{ $product->name }}">
                @endif

                <x-admin.checkbox name="featured" label="Feature on home page" :checked="old('featured', $product->featured)" />
                <x-admin.button type="submit">Save piece</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
