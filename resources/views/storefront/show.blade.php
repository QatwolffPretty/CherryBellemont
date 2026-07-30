@inject('settings', '\\App\\Services\\SettingsService')
<x-layouts.store
    :title="$product->name.' | Cherry Bellemont'"
    :meta-description="\Illuminate\Support\Str::limit(trim(strip_tags((string) $product->description)), 155, '')"
    :meta-image="$product->primaryImagePath() ? asset('storage/'.$product->primaryImagePath()) : null"
    :structured-data="$productStructuredData"
>
    @php
        $gallery = $product->productImages;
        $galleryFallback = $gallery->isEmpty() && $product->image_path ? collect([(object) ['image_path' => $product->image_path, 'alt_text' => $product->name]]) : $gallery;
        $variantOptions = $product->activeVariants->map(fn ($variant) => ['id' => $variant->id, 'size_id' => $variant->product_size_id, 'colour_id' => $variant->product_colour_id, 'sku' => $variant->sku, 'stock' => $variant->stock, 'price' => $variant->price_override ? (float) $variant->price_override : (float) $product->price])->values();
        $requiresSize = $variantOptions->contains(fn ($variant) => $variant['size_id'] !== null);
        $requiresColour = $variantOptions->contains(fn ($variant) => $variant['colour_id'] !== null);
        $variantSelectionLabel = $requiresColour && $requiresSize ? 'Select Colour & Size' : ($requiresColour ? 'Select Colour' : 'Select Size');
    @endphp
    <section class="mx-auto grid max-w-6xl gap-12 px-6 py-16 md:grid-cols-2">
        <div>
            <div class="aspect-[3/4] overflow-hidden border border-gold/20 bg-wine-deep">
                @if($galleryFallback->isNotEmpty())
                    @php($primaryGalleryImage = $galleryFallback->firstWhere('is_primary', true) ?: $galleryFallback->first())
                    <img id="product-main-image" class="h-full w-full object-cover" src="{{ asset('storage/'.$primaryGalleryImage->image_path) }}" alt="{{ $primaryGalleryImage->alt_text ?: $product->name }}">
                @else
                    <div class="flex h-full items-center justify-center border border-cream/10 text-center"><span class="font-display text-2xl tracking-[.2em] text-gold/70">CHERRY<br>BELLEMONT</span></div>
                @endif
            </div>
            @if($galleryFallback->count() > 1)
                <div class="mt-4 grid grid-cols-5 gap-3" aria-label="Product image gallery">
                    @foreach($galleryFallback as $image)
                        <button class="aspect-[3/4] overflow-hidden border {{ ($image->is_primary ?? false) ? 'border-gold' : 'border-cream/20' }} bg-wine-deep" type="button" data-gallery-thumbnail data-image-src="{{ asset('storage/'.$image->image_path) }}" data-image-alt="{{ $image->alt_text ?: $product->name }}"><img class="h-full w-full object-cover" src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?: $product->name }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col justify-center">
            <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs uppercase tracking-[.16em] text-gold"><a class="hover:text-cream" href="{{ route('collection') }}">Collection</a>@foreach($product->categories as $category)<span aria-hidden="true">/</span><a class="hover:text-cream" href="{{ route('collection.category', ['slug' => $category->slug]) }}">{{ $category->name }}</a>@endforeach</div>
            <h1 class="mt-4 text-5xl">{{ $product->name }}</h1>
            <p class="mt-4 text-2xl text-gold">RM {{ number_format($product->price, 2) }}</p>
            <div class="mt-4 flex items-center gap-3">
                <x-reviews.stars :rating="$reviewStats->average_rating" />
                <a class="text-sm text-cream/70 hover:text-gold" href="#reviews">{{ number_format((float) $reviewStats->average_rating, 1) }} ({{ $reviewStats->review_count }} {{ \Illuminate\Support\Str::plural('Review', $reviewStats->review_count) }})</a>
            </div>
            <p class="mt-8 whitespace-pre-line text-xl leading-relaxed text-cream/85">{{ $product->description }}</p>
            @if(! $hasVariants && ($product->sizes->isNotEmpty() || $product->colours->isNotEmpty() || $product->tags->isNotEmpty()))
                <div class="mt-8 border-y border-cream/15 py-6 text-sm">
                    @if($product->sizes->isNotEmpty())<div><p class="uppercase tracking-[.14em] text-gold">Available sizes</p><p class="mt-3 text-cream/80">{{ $product->sizes->pluck('name')->implode(' · ') }}</p></div>@endif
                    @if($product->colours->isNotEmpty())<div class="mt-5"><p class="uppercase tracking-[.14em] text-gold">Available colours</p><div class="mt-3 flex flex-wrap gap-4">@foreach($product->colours as $colour)<span class="inline-flex items-center gap-2 text-cream/80">@if($colour->hex_code)<span class="h-4 w-4 border border-gold/50" style="background-color: {{ $colour->hex_code }}"></span>@endif {{ $colour->name }}</span>@endforeach</div></div>@endif
                    @if($product->tags->isNotEmpty())<div class="mt-5"><p class="uppercase tracking-[.14em] text-gold">Collection</p><p class="mt-3 text-cream/80">{{ $product->tags->pluck('name')->implode(' · ') }}</p></div>@endif
                    <p class="mt-5 text-xs leading-5 text-cream/55">This piece uses product-level stock and does not require a selectable variant.</p>
                </div>
            @endif
            @if($availableStock > 0)
                <form id="product-cart-form" class="mt-10 space-y-6" method="POST" action="{{ route('cart.store', $product) }}" @if($hasVariants) data-variants="{{ json_encode($variantOptions) }}" data-base-price="{{ (float) $product->price }}" data-requires-size="{{ $requiresSize ? 'true' : 'false' }}" data-requires-colour="{{ $requiresColour ? 'true' : 'false' }}" data-select-label="{{ $variantSelectionLabel }}" @endif>
                    @csrf
                    @if($hasVariants)
                        <input type="hidden" name="variant_id" id="selected-variant-id">
                        <input type="hidden" name="size_id" id="selected-size-id">
                        <input type="hidden" name="colour_id" id="selected-colour-id">
                        @if($requiresColour)
                            <fieldset><legend class="uppercase tracking-[.14em] text-gold">Choose colour</legend><div class="mt-3 flex flex-wrap gap-3">@foreach($product->colours as $colour)<button class="border border-cream/25 px-4 py-3 text-sm text-cream transition hover:border-gold disabled:cursor-not-allowed disabled:opacity-35" type="button" data-colour-option="{{ $colour->id }}" aria-pressed="false">@if($colour->hex_code)<span class="mr-2 inline-block h-3 w-3 border border-gold/50 align-middle" style="background-color: {{ $colour->hex_code }}"></span>@endif{{ $colour->name }}</button>@endforeach</div>@error('colour')<p class="mt-2 text-gold">{{ $message }}</p>@enderror</fieldset>
                        @endif
                        @if($requiresSize)
                            <fieldset><legend class="uppercase tracking-[.14em] text-gold">Choose size</legend><div class="mt-3 flex flex-wrap gap-3">@foreach($product->sizes as $size)<button class="border border-cream/25 px-4 py-3 text-sm text-cream transition hover:border-gold disabled:cursor-not-allowed disabled:opacity-35" type="button" data-size-option="{{ $size->id }}" aria-pressed="false">{{ $size->name }}</button>@endforeach</div>@error('size')<p class="mt-2 text-gold">{{ $message }}</p>@enderror</fieldset>
                        @endif
                        <p id="variant-status" class="text-sm text-cream/65" aria-live="polite">Choose {{ $requiresColour && $requiresSize ? 'a colour and size' : ($requiresColour ? 'a colour' : 'a size') }} to continue.</p>
                    @endif
                    <div class="flex items-center gap-4"><input id="product-quantity" class="field w-20" type="number" name="quantity" min="1" max="{{ $hasVariants ? 1 : $availableStock }}" value="1" @disabled($hasVariants)><button id="add-to-bag" class="luxury-link disabled:cursor-not-allowed disabled:opacity-45" type="submit" @disabled($hasVariants) @if($hasVariants) aria-disabled="true" @endif>{{ $hasVariants ? $variantSelectionLabel : 'Add to Bag' }}</button></div>
                </form>
                @error('variant')<p class="mt-3 text-gold">{{ $message }}</p>@enderror
                @error('quantity')<p class="mt-3 text-gold">{{ $message }}</p>@enderror
                <p id="product-stock-status" class="mt-5 text-sm text-cream/55">{{ $hasVariants ? 'Select options to see availability.' : $availableStock.' currently available.' }}</p>
            @else
                <div class="mt-10 border-y border-gold/35 py-7">
                    <p class="uppercase tracking-[.2em] text-gold">Out of Stock</p>
                    <h2 class="mt-3 text-2xl">Notify Me When Available</h2>
                    <p class="mt-3 max-w-xl leading-7 text-cream/75">Enter your email and we’ll let you know when this Cherry Bellemont piece becomes available again.</p>
                    @if(session('stock_notification_success'))<p class="mt-5 border border-gold/50 p-4 text-gold">{{ session('stock_notification_success') }}</p>@endif
                    @error('stock_notification')<p class="mt-5 border border-gold/50 p-4 text-gold">{{ $message }}</p>@enderror
                    @if($settings->get('inventory.back_in_stock_enabled', true))
                    <form class="mt-6 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('product-stock-notifications.store', $product) }}">
                        @csrf
                        <input class="field" type="email" name="email" value="{{ old('email') }}" placeholder="Email Address" required>
                        <input class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Optional Name">
                        <div class="sm:col-span-2"><button class="luxury-link" type="submit">Notify Me</button></div>
                        @error('email')<p class="sm:col-span-2 text-gold">{{ $message }}</p>@enderror
                        @error('name')<p class="sm:col-span-2 text-gold">{{ $message }}</p>@enderror
                    </form>
                    @else
                        <p class="mt-5 text-sm text-cream/65">Please check back soon for availability.</p>
                    @endif
                </div>
            @endif

            @if($reviewContext)
                <a class="luxury-link mt-8 inline-block self-start" href="{{ route('reviews.create', ['product' => $product, ...request()->only(['order_number', 'guest_access_token', 'customer_email'])]) }}">
                    {{ $reviewContext['review'] ? 'Edit your verified review' : 'Write a verified review' }}
                </a>
            @endif
        </div>
    </section>

    <script>
        (() => {
            document.querySelectorAll('[data-gallery-thumbnail]').forEach((thumbnail) => thumbnail.addEventListener('click', () => {
                const main = document.getElementById('product-main-image');
                if (!main) return;
                main.src = thumbnail.dataset.imageSrc;
                main.alt = thumbnail.dataset.imageAlt;
                document.querySelectorAll('[data-gallery-thumbnail]').forEach((item) => item.classList.replace('border-gold', 'border-cream/20'));
                thumbnail.classList.replace('border-cream/20', 'border-gold');
            }));

            const form = document.getElementById('product-cart-form');
            if (!form?.dataset.variants) return;
            const variants = JSON.parse(form.dataset.variants);
            const requiresSize = form.dataset.requiresSize === 'true';
            const requiresColour = form.dataset.requiresColour === 'true';
            const selectLabel = form.dataset.selectLabel || 'Select options';
            const selected = { size: null, colour: null };
            const sizeInput = document.getElementById('selected-size-id');
            const colourInput = document.getElementById('selected-colour-id');
            const variantInput = document.getElementById('selected-variant-id');
            const quantity = document.getElementById('product-quantity');
            const add = document.getElementById('add-to-bag');
            const status = document.getElementById('variant-status');
            const stockStatus = document.getElementById('product-stock-status');
            const price = document.querySelector('.text-2xl.text-gold');
            const candidateFor = (size = selected.size, colour = selected.colour) => variants.filter((variant) => (!size || String(variant.size_id) === String(size)) && (!colour || String(variant.colour_id) === String(colour)));
            const setOptionState = () => {
                form.querySelectorAll('[data-size-option]').forEach((button) => { const enabled = candidateFor(button.dataset.sizeOption, selected.colour).some((variant) => variant.stock > 0); button.disabled = !enabled; button.setAttribute('aria-pressed', String(String(selected.size) === button.dataset.sizeOption)); button.classList.toggle('border-gold', String(selected.size) === button.dataset.sizeOption); });
                form.querySelectorAll('[data-colour-option]').forEach((button) => { const enabled = candidateFor(selected.size, button.dataset.colourOption).some((variant) => variant.stock > 0); button.disabled = !enabled; button.setAttribute('aria-pressed', String(selected.colour) === button.dataset.colourOption); button.classList.toggle('border-gold', String(selected.colour) === button.dataset.colourOption); });
            };
            const resolve = () => {
                setOptionState();
                sizeInput.value = selected.size || '';
                colourInput.value = selected.colour || '';
                const exact = variants.find((variant) => String(variant.size_id ?? '') === String(selected.size ?? '') && String(variant.colour_id ?? '') === String(selected.colour ?? ''));
                const ready = (!requiresSize || selected.size) && (!requiresColour || selected.colour);
                variantInput.value = exact?.id || '';
                const available = ready && exact && exact.stock > 0;
                quantity.disabled = !available;
                add.disabled = !available;
                add.setAttribute('aria-disabled', String(!available));
                quantity.max = available ? exact.stock : 1;
                if (available) { add.textContent = 'Add to Bag'; status.textContent = `SKU ${exact.sku || '—'} · ${exact.stock} available`; stockStatus.textContent = `${exact.stock} currently available for this selection.`; if (price) price.textContent = `RM ${Number(exact.price).toFixed(2)}`; }
                else if (ready) { add.textContent = 'Out of Stock'; status.textContent = 'This combination is out of stock.'; stockStatus.textContent = 'Out of stock for this selection.'; }
                else { add.textContent = selectLabel; status.textContent = `Choose the required ${requiresColour && requiresSize ? 'colour and size' : (requiresColour ? 'colour' : 'size')} to continue.`; }
            };
            form.querySelectorAll('[data-size-option]').forEach((button) => button.addEventListener('click', () => { selected.size = button.dataset.sizeOption; resolve(); }));
            form.querySelectorAll('[data-colour-option]').forEach((button) => button.addEventListener('click', () => { selected.colour = button.dataset.colourOption; resolve(); }));
            form.addEventListener('submit', (event) => {
                const exact = variants.find((variant) => String(variant.size_id ?? '') === String(selected.size ?? '') && String(variant.colour_id ?? '') === String(selected.colour ?? ''));
                if (!exact || exact.stock < 1) {
                    event.preventDefault();
                    variantInput.value = '';
                    status.textContent = exact ? 'This combination is out of stock.' : `Choose the required ${requiresColour && requiresSize ? 'colour and size' : (requiresColour ? 'colour' : 'size')} to continue.`;
                    resolve();
                }
            });
            resolve();
        })();
    </script>

    @if(session('success'))<p class="mx-auto max-w-6xl border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif

    <section id="reviews" class="border-t border-cream/15 px-6 py-16">
        <div class="mx-auto max-w-6xl">
            <div class="grid gap-10 lg:grid-cols-[18rem_1fr]">
                <aside class="border border-cream/15 p-6">
                    <p class="uppercase tracking-[.2em] text-gold">Client reviews</p>
                    <div class="mt-5 flex items-end gap-3"><span class="text-5xl text-gold">{{ number_format((float) $reviewStats->average_rating, 1) }}</span><x-reviews.stars :rating="$reviewStats->average_rating" /></div>
                    <p class="mt-2 text-sm text-cream/65">{{ $reviewStats->review_count }} {{ str('review')->plural($reviewStats->review_count) }}</p>
                    <div class="mt-7 space-y-3">
                        @for($rating = 5; $rating >= 1; $rating--)
                            @php($count = (int) ($ratingBreakdown[$rating] ?? 0))
                            <div class="flex items-center gap-3 text-sm"><span class="w-10 text-gold">{{ $rating }} ★</span><span class="h-px flex-1 bg-cream/20"><span class="block h-px bg-gold" style="width: {{ $reviewStats->review_count ? ($count / $reviewStats->review_count) * 100 : 0 }}%"></span></span><span class="w-6 text-right">{{ $count }}</span></div>
                        @endfor
                    </div>
                </aside>

                <div>
                    <div class="flex flex-wrap items-end justify-between gap-5">
                        <div><p class="uppercase tracking-[.2em] text-gold">Reviews</p><h2 class="mt-3 text-4xl">Loved by the collection.</h2></div>
                    </div>

                    <form class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" method="GET">
                        <input class="field lg:col-span-2" name="review_search" value="{{ request('review_search') }}" placeholder="Search reviews">
                        <select class="field" name="rating"><option value="">All stars</option>@foreach([5,4,3,2,1] as $rating)<option value="{{ $rating }}" @selected((int) request('rating') === $rating)>{{ $rating }} stars</option>@endforeach</select>
                        <select class="field" name="review_sort"><option value="">Newest</option><option value="oldest" @selected(request('review_sort') === 'oldest')>Oldest</option><option value="highest" @selected(request('review_sort') === 'highest')>Highest rating</option><option value="lowest" @selected(request('review_sort') === 'lowest')>Lowest rating</option><option value="helpful" @selected(request('review_sort') === 'helpful')>Most helpful</option></select>
                        <button class="luxury-link" type="submit">Filter</button>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified'))> Verified purchase</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="with_images" value="1" @checked(request()->boolean('with_images'))> With images</label>
                    </form>

                    @forelse($reviews as $review)
                        <article class="mt-8 border-t border-cream/15 pt-8">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div><x-reviews.stars :rating="$review->rating" /><h3 class="mt-3 text-2xl">{{ $review->title }}</h3></div>
                                <span class="text-sm text-cream/60">{{ $review->created_at->format('d M Y') }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm"><span>{{ $review->customerFirstName() }}</span>@if($review->is_verified_purchase)<x-reviews.verified-badge />@endif</div>
                            <p class="mt-4 whitespace-pre-line leading-7 text-cream/80">{{ $review->review }}</p>
                            @if($review->images->isNotEmpty())
                                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-5">@foreach($review->images as $image)<a href="{{ asset('storage/'.$image->image_path) }}" target="_blank" rel="noopener"><img class="aspect-square w-full border border-cream/15 object-cover" src="{{ asset('storage/'.$image->image_path) }}" alt="Review photo for {{ $product->name }}"></a>@endforeach</div>
                            @endif
                            @if($review->admin_reply)
                                <div class="mt-6 border-l border-gold pl-5"><p class="uppercase tracking-[.14em] text-gold">Cherry Bellemont — Official Response</p><p class="mt-3 whitespace-pre-line text-cream/80">{{ $review->admin_reply }}</p></div>
                            @endif
                            <form class="mt-5" method="POST" action="{{ route('reviews.helpful', $review) }}">@csrf <button class="nav-link" type="submit"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Helpful ({{ $review->helpful_count }})</button></form>
                        </article>
                    @empty
                        <div class="mt-8 border border-cream/15 p-10 text-center"><h3 class="text-2xl">No Reviews Yet</h3><p class="mt-3 text-cream/65">Be the first to review this product after your delivered purchase.</p></div>
                    @endforelse
                    <div class="mt-10">{{ $reviews->links() }}</div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.store>
