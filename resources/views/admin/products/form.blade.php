<x-layouts.admin :title="($product->exists ? 'Edit' : 'Add').' piece | Cherry Bellemont'">
    @php
        $selectedCategories = old('category_ids', $product->exists ? $product->categories->pluck('id')->all() : []);
        $selectedSizes = old('size_ids', $product->exists ? $product->sizes->pluck('id')->all() : []);
        $selectedColours = old('colour_ids', $product->exists ? $product->colours->pluck('id')->all() : []);
        $selectedTags = old('tag_ids', $product->exists ? $product->tags->pluck('id')->all() : []);
        $primaryCategoryId = old('primary_category_id', $product->exists ? $product->primaryCategory->first()?->id : null);
        $variantRows = old('variants', $product->exists ? $product->variants->map(fn ($variant) => [
            'id' => $variant->id, 'size_id' => $variant->product_size_id, 'colour_id' => $variant->product_colour_id,
            'sku' => $variant->sku, 'stock' => $variant->stock, 'price_override' => $variant->price_override,
            'is_active' => $variant->is_active,
        ])->all() : []);
        $galleryImages = $product->exists ? $product->productImages : collect();
    @endphp
    <x-admin.section width="2xl">
        <x-admin.page-header :title="$product->exists ? 'Edit piece' : 'Add a piece'" />

        <x-admin.card class="mt-10">
            <form id="product-form" class="space-y-6" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
                @csrf
                @if($product->exists) @method('PUT') @endif

                <x-admin.form-input name="name" label="Name" :value="$product->name" required />
                <x-admin.textarea name="description" label="Description" :value="$product->description" class="min-h-32" />
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-admin.form-input name="price" label="Base price (RM)" type="number" step="0.01" min="0" :value="$product->price" required />
                    <x-admin.form-input name="cost_price" label="Unit cost (RM)" type="number" step="0.01" min="0" :value="$product->cost_price" help="Used only for future order cost snapshots and accounting." />
                </div>
                <x-admin.form-input name="stock" label="Available stock" type="number" min="0" :value="$product->stock ?? 0" required help="For products with variants, this becomes the total of active variant stock after saving." />

                <div class="border-y border-cream/15 py-6">
                    <p class="text-lg text-gold">Catalogue assignment</p>
                    <p class="mt-2 text-sm leading-6 text-cream/60">Select the sizes and colours that this piece offers, then generate only the sellable combinations below.</p>
                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <x-admin.select name="primary_category_id" label="Primary category"><option value="">No primary category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) $primaryCategoryId === $category->id) @disabled(! $category->is_active)>{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}</option>@endforeach</x-admin.select>
                        <div><span class="admin-label">Additional categories</span><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($categories as $category)<label class="flex items-center gap-3 text-sm text-cream/85"><input class="admin-check" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, array_map('intval', (array) $selectedCategories), true))> <span>{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}</span></label>@endforeach</div>@error('category_ids')<x-admin.validation-error :message="$message" />@enderror</div>
                    </div>
                    <div class="mt-7 grid gap-6 lg:grid-cols-3">
                        <div><span class="admin-label">Available sizes</span><div id="size-options" class="mt-3 grid gap-3">@forelse($sizes as $size)<label class="flex items-center gap-3 text-sm text-cream/85"><input class="admin-check catalogue-size" type="checkbox" name="size_ids[]" value="{{ $size->id }}" data-name="{{ $size->name }}" @checked(in_array($size->id, array_map('intval', (array) $selectedSizes), true))> <span>{{ $size->name }}{{ $size->is_active ? '' : ' (inactive)' }}</span></label>@empty<p class="text-sm text-cream/60">No sizes created yet.</p>@endforelse</div></div>
                        <div><span class="admin-label">Available colours</span><div id="colour-options" class="mt-3 grid gap-3">@forelse($colours as $colour)<label class="flex items-center gap-3 text-sm text-cream/85"><input class="admin-check catalogue-colour" type="checkbox" name="colour_ids[]" value="{{ $colour->id }}" data-name="{{ $colour->name }}" @checked(in_array($colour->id, array_map('intval', (array) $selectedColours), true))> <span class="inline-block h-4 w-4 border border-gold/40" @if($colour->hex_code) style="background-color: {{ $colour->hex_code }}" @endif></span><span>{{ $colour->name }}{{ $colour->is_active ? '' : ' (inactive)' }}</span></label>@empty<p class="text-sm text-cream/60">No colours created yet.</p>@endforelse</div></div>
                        <div><span class="admin-label">Collection tags</span><div class="mt-3 grid gap-3">@forelse($tags as $tag)<label class="flex items-center gap-3 text-sm text-cream/85"><input class="admin-check" type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id, array_map('intval', (array) $selectedTags), true))> <span>{{ $tag->name }}{{ $tag->is_active ? '' : ' (inactive)' }}</span></label>@empty<p class="text-sm text-cream/60">No tags created yet.</p>@endforelse</div></div>
                    </div>
                </div>

                <div class="border-y border-cream/15 py-6">
                    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-lg text-gold">Sellable variants</p><p class="mt-2 text-sm leading-6 text-cream/60">Generate the size and colour combinations this product can sell. Variant stock is the source of truth for products with options.</p></div><button id="generate-variants" class="admin-button admin-button-outline" type="button"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Generate variants</button></div>
                    @error('variants')<p class="mt-3 text-sm text-gold">{{ $message }}</p>@enderror
                    <div class="mt-6 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b border-cream/15 text-xs uppercase tracking-[.14em] text-gold"><tr><th class="px-3 py-3">Colour</th><th class="px-3 py-3">Size</th><th class="px-3 py-3">SKU</th><th class="px-3 py-3">Stock</th><th class="px-3 py-3">Price override</th><th class="px-3 py-3">Active</th></tr></thead><tbody id="variants-body">
                        @foreach($variantRows as $rowKey => $row)
                            @php($key = $row['id'] ?? $rowKey)
                            <tr data-combination="{{ $row['size_id'] ?? 'none' }}-{{ $row['colour_id'] ?? 'none' }}" class="border-b border-cream/10"><td class="px-3 py-3">{{ $colours->firstWhere('id', (int) ($row['colour_id'] ?? 0))?->name ?: '—' }}<input type="hidden" name="variants[{{ $key }}][colour_id]" value="{{ $row['colour_id'] }}"></td><td class="px-3 py-3">{{ $sizes->firstWhere('id', (int) ($row['size_id'] ?? 0))?->name ?: '—' }}<input type="hidden" name="variants[{{ $key }}][size_id]" value="{{ $row['size_id'] }}">@if(!empty($row['id']))<input type="hidden" name="variants[{{ $key }}][id]" value="{{ $row['id'] }}">@endif</td><td class="px-3 py-3"><input class="field min-w-36" name="variants[{{ $key }}][sku]" value="{{ $row['sku'] }}" maxlength="120"></td><td class="px-3 py-3"><input class="field w-24" type="number" min="0" name="variants[{{ $key }}][stock]" value="{{ $row['stock'] ?? 0 }}" required></td><td class="px-3 py-3"><input class="field w-28" type="number" min="0" step="0.01" name="variants[{{ $key }}][price_override]" value="{{ $row['price_override'] }}" placeholder="Base"></td><td class="px-3 py-3"><input type="hidden" name="variants[{{ $key }}][is_active]" value="0"><input class="admin-check" type="checkbox" name="variants[{{ $key }}][is_active]" value="1" @checked($row['is_active'] ?? true)></td></tr>
                        @endforeach
                    </tbody></table></div>
                    <p class="mt-3 text-xs leading-5 text-cream/55">Existing combinations are never overwritten by generation. Ordered variants are retained for history and can be deactivated from their dedicated action.</p>
                </div>

                <div class="border-y border-cream/15 py-6">
                    <p class="text-lg text-gold">Product gallery</p>
                    <p class="mt-2 text-sm leading-6 text-cream/60">Upload up to 10 JPG, PNG, or WEBP images. The first image becomes the primary image when none is selected.</p>
                    <label class="admin-label mt-5 block" for="images">Add gallery images</label>
                    <input id="images" class="field mt-2" type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    @error('images')<p class="mt-2 text-sm text-gold">{{ $message }}</p>@enderror
                    @error('images.*')<p class="mt-2 text-sm text-gold">{{ $message }}</p>@enderror
                    <div id="new-image-preview" class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4"></div>
                </div>

                @if($product->exists)
                    <div class="grid gap-4 border-y border-cream/15 py-5 sm:grid-cols-2"><p><span class="block text-sm text-cream/60">Waiting for restock</span><strong class="mt-1 block text-2xl text-gold">{{ $product->waiting_stock_notifications_count ?? 0 }}</strong></p><p><span class="block text-sm text-cream/60">Previously notified</span><strong class="mt-1 block text-2xl text-gold">{{ $product->notified_stock_notifications_count ?? 0 }}</strong></p></div>
                    <x-admin.button variant="outline" :href="route('admin.product-stock-notifications.index', ['product_id' => $product->id])" icon="bi-bell">View Requests</x-admin.button>
                @endif
                <x-admin.select name="status" label="Status">@foreach(['draft', 'active', 'archived'] as $status)<option value="{{ $status }}" @selected(old('status', $product->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</x-admin.select>
                <x-admin.checkbox name="featured" label="Feature on home page" :checked="old('featured', $product->featured)" />
                <x-admin.button type="submit">Save piece</x-admin.button>
            </form>

            @if($product->exists && $galleryImages->isNotEmpty())
                <div class="mt-10 border-t border-cream/15 pt-7"><div class="flex flex-wrap items-center justify-between gap-4"><div><p class="text-lg text-gold">Existing gallery</p><p class="mt-1 text-sm text-cream/60">Choose the primary image, adjust display order, or remove a single image.</p></div><button class="admin-button admin-button-outline" type="submit" form="gallery-order-form">Save image order</button></div><form id="gallery-order-form" method="POST" action="{{ route('admin.products.images.sort', $product) }}">@csrf @method('PATCH')<div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($galleryImages as $image)<article class="border border-cream/15 p-3"><img class="aspect-[3/4] w-full object-cover" src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?: $product->name }}"><div class="mt-3 flex items-center justify-between gap-2"><label class="text-xs text-cream/60">Order<input class="field mt-1 w-20" type="number" min="0" name="images[{{ $image->id }}]" value="{{ $image->sort_order }}"></label>@if($image->is_primary)<span class="text-xs uppercase tracking-[.12em] text-gold">Primary</span>@endif</div><div class="mt-4 flex flex-wrap gap-3"><button class="nav-link" type="submit" form="set-primary-image-{{ $image->id }}">Set primary</button><button class="nav-link text-gold" type="submit" form="delete-product-image-{{ $image->id }}">Remove</button></div></article>@endforeach</div></form>@foreach($galleryImages as $image)<form id="set-primary-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.primary', [$product, $image]) }}">@csrf @method('PATCH')</form><form id="delete-product-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Remove this gallery image?')">@csrf @method('DELETE')</form>@endforeach</div>
            @endif
        </x-admin.card>
    </x-admin.section>

    <script>
        (() => {
            const input = document.getElementById('images');
            const preview = document.getElementById('new-image-preview');
            input?.addEventListener('change', () => {
                preview.replaceChildren();
                Array.from(input.files || []).slice(0, 10).forEach((file, index) => {
                    const card = document.createElement('div');
                    const image = document.createElement('img');
                    image.className = 'aspect-[3/4] w-full border border-gold/30 object-cover';
                    image.alt = 'Selected product image preview';
                    image.src = URL.createObjectURL(file);
                    const alt = document.createElement('input');
                    alt.className = 'field mt-2 w-full text-xs';
                    alt.name = 'image_alt_texts[]';
                    alt.maxLength = 255;
                    alt.placeholder = `Optional alt text for image ${index + 1}`;
                    card.append(image, alt);
                    preview.append(card);
                });
            });

            const body = document.getElementById('variants-body');
            const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
            document.getElementById('generate-variants')?.addEventListener('click', () => {
                const sizes = [...document.querySelectorAll('.catalogue-size:checked')].map((input) => ({ id: input.value, name: input.dataset.name }));
                const colours = [...document.querySelectorAll('.catalogue-colour:checked')].map((input) => ({ id: input.value, name: input.dataset.name }));
                const sizeValues = sizes.length ? sizes : [{ id: '', name: '—' }];
                const colourValues = colours.length ? colours : [{ id: '', name: '—' }];
                if (!sizes.length && !colours.length) return;
                sizeValues.forEach((size) => colourValues.forEach((colour) => {
                    const combination = `${size.id || 'none'}-${colour.id || 'none'}`;
                    if (body.querySelector(`[data-combination="${combination}"]`)) return;
                    const key = `new-${size.id || 'none'}-${colour.id || 'none'}`;
                    const row = document.createElement('tr');
                    row.dataset.combination = combination;
                    row.className = 'border-b border-cream/10';
                    row.innerHTML = `<td class="px-3 py-3">${escapeHtml(colour.name)}<input type="hidden" name="variants[${key}][colour_id]" value="${colour.id}"></td><td class="px-3 py-3">${escapeHtml(size.name)}<input type="hidden" name="variants[${key}][size_id]" value="${size.id}"></td><td class="px-3 py-3"><input class="field min-w-36" name="variants[${key}][sku]" maxlength="120"></td><td class="px-3 py-3"><input class="field w-24" type="number" min="0" name="variants[${key}][stock]" value="0" required></td><td class="px-3 py-3"><input class="field w-28" type="number" min="0" step="0.01" name="variants[${key}][price_override]" placeholder="Base"></td><td class="px-3 py-3"><input type="hidden" name="variants[${key}][is_active]" value="0"><input class="admin-check" type="checkbox" name="variants[${key}][is_active]" value="1" checked></td>`;
                    body.append(row);
                }));
            });
        })();
    </script>
</x-layouts.admin>
