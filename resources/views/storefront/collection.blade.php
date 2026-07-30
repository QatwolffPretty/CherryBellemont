<x-layouts.store
    :title="$category ? ($category->meta_title ?: $category->name.' | Cherry Bellemont') : 'Collection | Cherry Bellemont'"
    :meta-description="$category ? ($category->meta_description ?: $category->description) : 'Explore the Cherry Bellemont collection of refined women’s pieces.'"
    :meta-image="$category?->image_path ? asset('storage/'.$category->image_path) : null"
    :structured-data="$categoryStructuredData"
>
    @php
        $clearUrl = $category ? route('collection.category', ['slug' => $category->slug]) : route('collection');
        $checked = fn (string $group, string $value) => in_array($value, $filters[$group], true);
        $filterCount = count($filters['category']) + count($filters['size']) + count($filters['colour']) + count($filters['tag']) + ($filters['min_price'] !== null ? 1 : 0) + ($filters['max_price'] !== null ? 1 : 0) + ($filters['availability'] !== 'all' ? 1 : 0);
    @endphp
    <section class="mx-auto max-w-7xl px-6 py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="uppercase tracking-[.25em] text-gold">{{ $category ? 'The edit' : 'The collection' }}</p>
            <h1 class="mt-3 text-4xl sm:text-5xl">{{ $category?->name ?: 'Quiet distinction.' }}</h1>
            @if($category?->description)<p class="mt-5 max-w-2xl text-lg leading-8 text-cream/75">{{ $category->description }}</p>@endif
            @if($category?->image_path)<img class="mt-8 aspect-[5/2] w-full border border-gold/20 object-cover" src="{{ asset('storage/'.$category->image_path) }}" alt="{{ $category->name }}">@endif
        </div>

        <form class="mt-10 border-y border-cream/15 py-6" method="GET" action="{{ $category ? route('collection.category', ['slug' => $category->slug]) : route('collection') }}">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_15rem_auto]">
                <input class="field" name="search" value="{{ $filters['search'] }}" placeholder="Search by piece, category, colour or collection tag">
                <select class="field" name="sort" aria-label="Sort products">
                    @foreach($sortOptions as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach
                </select>
                <button class="luxury-link" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
            </div>

            <details class="mt-6 border-t border-cream/15 pt-5" @if($filterCount > 0) open @endif>
                <summary class="cursor-pointer select-none text-sm uppercase tracking-[.16em] text-gold"><i class="bi bi-funnel" aria-hidden="true"></i> Filter{{ $filterCount ? ' ('.$filterCount.')' : '' }}</summary>
                <div class="mt-6 grid gap-8 md:grid-cols-2 xl:grid-cols-5">
                    <fieldset><legend class="text-sm uppercase tracking-[.14em] text-cream/75">Category</legend><div class="mt-4 grid gap-3">@foreach($filterOptions['categories'] as $option)<label class="flex items-center gap-3 text-sm text-cream/80"><input type="checkbox" name="category[]" value="{{ $option->slug }}" @checked($checked('category', $option->slug))> {{ $option->name }}</label>@endforeach</div></fieldset>
                    <fieldset><legend class="text-sm uppercase tracking-[.14em] text-cream/75">Size</legend><div class="mt-4 grid grid-cols-2 gap-3">@forelse($filterOptions['sizes'] as $option)<label class="flex items-center gap-2 text-sm text-cream/80"><input type="checkbox" name="size[]" value="{{ strtolower($option->code) }}" @checked($checked('size', $option->code))> {{ $option->name }}</label>@empty<p class="text-sm text-cream/60">No sizes available yet.</p>@endforelse</div></fieldset>
                    <fieldset><legend class="text-sm uppercase tracking-[.14em] text-cream/75">Colour</legend><div class="mt-4 grid gap-3">@forelse($filterOptions['colours'] as $option)<label class="flex items-center gap-3 text-sm text-cream/80"><input type="checkbox" name="colour[]" value="{{ $option->slug }}" @checked($checked('colour', $option->slug))>@if($option->hex_code)<span class="h-4 w-4 border border-gold/50" style="background-color: {{ $option->hex_code }}"></span>@endif {{ $option->name }}</label>@empty<p class="text-sm text-cream/60">No colours available yet.</p>@endforelse</div></fieldset>
                    <fieldset><legend class="text-sm uppercase tracking-[.14em] text-cream/75">Collection</legend><div class="mt-4 grid gap-3">@forelse($filterOptions['tags'] as $option)<label class="flex items-center gap-3 text-sm text-cream/80"><input type="checkbox" name="tag[]" value="{{ $option->slug }}" @checked($checked('tag', $option->slug))> {{ $option->name }}</label>@empty<p class="text-sm text-cream/60">No collection tags available yet.</p>@endforelse</div></fieldset>
                    <fieldset><legend class="text-sm uppercase tracking-[.14em] text-cream/75">Price & availability</legend><div class="mt-4 grid gap-3"><label class="text-sm text-cream/80">Minimum price<input class="field mt-2 w-full" type="number" name="min_price" min="0" step="0.01" value="{{ $filters['min_price'] }}" placeholder="RM 0"></label><label class="text-sm text-cream/80">Maximum price<input class="field mt-2 w-full" type="number" name="max_price" min="0" step="0.01" value="{{ $filters['max_price'] }}" placeholder="RM 200"></label><select class="field" name="availability"><option value="all" @selected($filters['availability'] === 'all')>All availability</option><option value="in_stock" @selected($filters['availability'] === 'in_stock')>In Stock</option><option value="out_of_stock" @selected($filters['availability'] === 'out_of_stock')>Out of Stock</option></select></div></fieldset>
                </div>
                <div class="mt-8 flex flex-wrap gap-4"><button class="luxury-link" type="submit">Apply filters</button><a class="nav-link self-center" href="{{ $clearUrl }}">Clear all filters</a></div>
            </details>
        </form>

        @if($filters['search'])<p class="mt-8 text-lg text-cream/75">Search results for <span class="text-gold">“{{ $filters['search'] }}”</span></p>@endif
        <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($products as $product)
                @php($primaryCategory = $product->primaryCategory->first())
                <article class="group">
                    <a href="{{ route('products.show', $product) }}">
                        <div class="relative aspect-[3/4] overflow-hidden border border-gold/20 bg-wine-deep">
                            @if($product->primaryImagePath())<img class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" src="{{ asset('storage/'.$product->primaryImagePath()) }}" alt="{{ $product->name }}">@else<div class="flex h-full items-center justify-center text-gold/70">CHERRY BELLEMONT</div>@endif
                            @if($product->tags->isNotEmpty())<span class="absolute left-3 top-3 border border-gold/55 bg-wine-deep/90 px-3 py-1 text-xs uppercase tracking-[.12em] text-gold">{{ $product->tags->first()->name }}</span>@endif
                            @if($product->stock < 1)<span class="absolute bottom-3 left-3 bg-wine px-3 py-1 text-xs uppercase tracking-[.12em] text-cream">Out of Stock</span>@endif
                        </div>
                        @if($primaryCategory)<p class="mt-4 text-xs uppercase tracking-[.16em] text-gold">{{ $primaryCategory->name }}</p>@endif
                        <h2 class="mt-2 text-2xl">{{ $product->name }}</h2>
                    </a>
                    <div class="mt-2 flex items-center gap-2 text-sm text-cream/70"><x-reviews.stars :rating="$product->approved_reviews_avg_rating ?? 0" size="text-xs" /><span>({{ $product->approved_reviews_count }})</span></div>
                    @if($product->colours->isNotEmpty())
                        <div class="mt-3 flex items-center gap-2" aria-label="Available colours">
                            @foreach($product->colours->take(4) as $colour)
                                <span class="h-3 w-3 border border-gold/50" @if($colour->hex_code) style="background-color: {{ $colour->hex_code }}" @endif title="{{ $colour->name }}"></span>
                            @endforeach
                            @if($product->colours->count() > 4)<span class="text-xs text-cream/60">+{{ $product->colours->count() - 4 }}</span>@endif
                        </div>
                    @endif
                    <div class="mt-3 flex items-center justify-between"><p class="text-gold">RM {{ number_format($product->price, 2) }}</p>@if($product->variants_count > 0)<a class="nav-link" href="{{ route('products.show', $product) }}">Choose options</a>@else<form method="POST" action="{{ route('cart.store', $product) }}">@csrf <input type="hidden" name="quantity" value="1"><button class="nav-link" type="submit" @disabled($product->stock < 1)>{{ $product->stock > 0 ? 'Add to bag' : 'Sold out' }}</button></form>@endif</div>
                </article>
            @empty
                <div class="col-span-full border border-cream/15 px-6 py-14 text-center"><h2 class="text-2xl">No pieces match your selection.</h2><p class="mt-3 text-cream/65">Try removing a filter or explore the full collection.</p><a class="luxury-link mt-6 inline-block" href="{{ $clearUrl }}">Clear all filters</a></div>
            @endforelse
        </div>
        <div class="mt-12">{{ $products->links() }}</div>
    </section>
</x-layouts.store>
