<x-layouts.store :title="($product->exists ? 'Edit' : 'Add').' piece | Cherry Bellemont'">
    <section class="mx-auto max-w-2xl px-6 py-16">
        <h1 class="text-4xl">{{ $product->exists ? 'Edit piece' : 'Add a piece' }}</h1>

        <form class="mt-10 space-y-6" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if($product->exists) @method('PUT') @endif

            <div><label>Name</label><input class="field mt-2" name="name" value="{{ old('name', $product->name) }}" required></div>
            <div><label>Description</label><textarea class="field mt-2 min-h-32" name="description">{{ old('description', $product->description) }}</textarea></div>
            <div><label>Price (RM)</label><input class="field mt-2" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" required></div>
            <div><label>Available stock</label><input class="field mt-2" type="number" min="0" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" required></div>
            <div><label>Status</label><select class="field mt-2" name="status">@foreach(['draft', 'active', 'archived'] as $status)<option value="{{ $status }}" @selected(old('status', $product->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>

            <div>
                <label for="image">Product image</label>
                <input id="image" class="field mt-2" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <p class="mt-2 text-sm text-cream/60">JPG, PNG, or WEBP up to 5 MB{{ $product->exists ? '. Leave blank to keep the current image.' : '' }}</p>
                @error('image')<p class="mt-2 text-sm text-gold">{{ $message }}</p>@enderror

                @if($product->image_path)
                    <img class="mt-4 aspect-[3/4] w-40 border border-gold/40 object-cover" src="{{ asset('storage/' . $product->image_path) }}" alt="Current image for {{ $product->name }}">
                @endif
            </div>

            <label class="flex gap-3"><input type="checkbox" name="featured" value="1" @checked(old('featured', $product->featured))> Feature on home page</label>
            <button class="luxury-link" type="submit">Save piece</button>
        </form>
    </section>
</x-layouts.store>
