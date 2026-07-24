@inject('settings', '\\App\\Services\\SettingsService')
<x-layouts.store
    :title="$product->name.' | Cherry Bellemont'"
    :meta-description="\Illuminate\Support\Str::limit(trim(strip_tags((string) $product->description)), 155, '')"
    :meta-image="$product->image_path ? asset('storage/'.$product->image_path) : null"
    :structured-data="$productStructuredData"
>
    <section class="mx-auto grid max-w-6xl gap-12 px-6 py-16 md:grid-cols-2">
        <div class="aspect-[3/4] overflow-hidden border border-gold/20 bg-wine-deep">
            @if($product->image_path)
                <img class="h-full w-full object-cover" src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}">
            @else
                <div class="flex h-full items-center justify-center border border-cream/10 text-center"><span class="font-display text-2xl tracking-[.2em] text-gold/70">CHERRY<br>BELLEMONT</span></div>
            @endif
        </div>

        <div class="flex flex-col justify-center">
            <p class="uppercase tracking-[.25em] text-gold">Cherry Bellemont</p>
            <h1 class="mt-4 text-5xl">{{ $product->name }}</h1>
            <p class="mt-4 text-2xl text-gold">RM {{ number_format($product->price, 2) }}</p>
            <div class="mt-4 flex items-center gap-3">
                <x-reviews.stars :rating="$reviewStats->average_rating" />
                <a class="text-sm text-cream/70 hover:text-gold" href="#reviews">{{ number_format((float) $reviewStats->average_rating, 1) }} ({{ $reviewStats->review_count }} {{ \Illuminate\Support\Str::plural('Review', $reviewStats->review_count) }})</a>
            </div>
            <p class="mt-8 whitespace-pre-line text-xl leading-relaxed text-cream/85">{{ $product->description }}</p>
            @if($product->stock > 0)
                <form class="mt-10 flex items-center gap-4" method="POST" action="{{ route('cart.store', $product) }}">
                    @csrf
                    <input class="field w-20" type="number" name="quantity" min="1" max="{{ $product->stock }}" value="1">
                    <button class="luxury-link" type="submit">Add to bag</button>
                </form>
                @error('quantity')<p class="mt-3 text-gold">{{ $message }}</p>@enderror
                <p class="mt-5 text-sm text-cream/55">{{ $product->stock }} currently available.</p>
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
