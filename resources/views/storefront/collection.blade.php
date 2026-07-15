<x-layouts.store title="Collection | Cherry Bellemont">
    <section class="mx-auto max-w-7xl px-6 py-20">
        <p class="uppercase tracking-[.25em] text-gold">The collection</p>
        <h1 class="mt-3 text-5xl">Quiet distinction.</h1>
        <form class="mt-10 grid gap-4 sm:grid-cols-[1fr_14rem_auto]" method="GET">
            <input class="field" name="search" value="{{ request('search') }}" placeholder="Search the collection">
            <select class="field" name="sort"><option value="">Newest</option><option value="price_asc" @selected(request('sort') === 'price_asc')>Price low–high</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Price high–low</option><option value="featured" @selected(request('sort') === 'featured')>Featured</option></select>
            <button class="luxury-link" type="submit">Refine</button>
        </form>

        <div class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($products as $product)
                <article class="group">
                    <a href="{{ route('products.show', $product) }}">
                        <div class="aspect-[3/4] overflow-hidden border border-gold/20 bg-wine-deep">
                            @if($product->image_path)<img class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}">@else<div class="flex h-full items-center justify-center text-gold/70">CHERRY BELLEMONT</div>@endif
                        </div>
                        <h2 class="mt-4 text-2xl">{{ $product->name }}</h2>
                    </a>
                    <div class="mt-2 flex items-center gap-2 text-sm text-cream/70"><x-reviews.stars :rating="$product->approved_reviews_avg_rating ?? 0" size="text-xs" /><span>({{ $product->approved_reviews_count }})</span></div>
                    <div class="mt-2 flex items-center justify-between"><p class="text-gold">RM {{ number_format($product->price, 2) }}</p><form method="POST" action="{{ route('cart.store', $product) }}">@csrf <input type="hidden" name="quantity" value="1"><button class="nav-link" type="submit" @disabled($product->stock < 1)>{{ $product->stock > 0 ? 'Add to bag' : 'Sold out' }}</button></form></div>
                </article>
            @empty
                <p class="col-span-full py-12 text-center text-cream/65">No pieces match your selection.</p>
            @endforelse
        </div>
        <div class="mt-12">{{ $products->links() }}</div>
    </section>
</x-layouts.store>
