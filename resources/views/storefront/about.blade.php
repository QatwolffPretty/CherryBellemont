<x-layouts.store title="About Cherry Bellemont">
    <section class="bg-wine px-6 py-20 md:py-28">
        <div class="mx-auto grid max-w-6xl gap-12 lg:grid-cols-2 lg:items-center lg:gap-16">
            <div class="border border-gold/30 bg-wine-deep p-5 sm:p-8">
                <div class="relative aspect-[4/5] overflow-hidden border border-cream/15 bg-wine">
                    <img src="{{ asset('images/cherry-mono.png') }}" alt="Cherry Bellemont monogram" class="absolute inset-0 m-auto h-3/4 w-3/4 object-contain opacity-25">
                    <div class="absolute inset-x-0 bottom-0 border-t border-gold/30 bg-wine-deep/90 px-6 py-5 text-center">
                        <p class="font-display text-2xl tracking-[.14em] text-cream">CHERRY BELLEMONT</p>
                        <p class="mt-2 text-xs uppercase tracking-[.22em] text-gold">The House of Quiet Distinction</p>
                    </div>
                </div>
            </div>

            <div>
                <p class="uppercase tracking-[.25em] text-gold">The Cherry Bellemont story</p>
                <h1 class="mt-4 text-5xl leading-tight md:text-6xl">About Cherry Bellemont</h1>
                <p class="mt-6 font-display text-2xl leading-relaxed text-gold">Crafted with Elegance. Designed for Every Occasion.</p>

                <div class="mt-8 space-y-5 leading-8 text-cream/80">
                    <p>Cherry Bellemont was founded with a simple belief: luxury should feel timeless, elegant, and effortlessly beautiful.</p>
                    <p>Every piece in our collection is carefully selected to reflect sophistication, confidence, and modern femininity. From everyday essentials to statement pieces, we focus on exceptional craftsmanship, refined details, and lasting quality.</p>
                    <p>Our mission is to offer more than products—we create an experience that celebrates style, individuality, and confidence with every purchase.</p>
                    <p>Whether you're shopping for yourself or someone special, Cherry Bellemont is dedicated to making every order feel luxurious from the moment you discover us until it arrives at your doorstep.</p>
                </div>

                <div class="mt-10 grid gap-px border border-gold/30 sm:grid-cols-2">
                    @foreach(['Premium Quality', 'Secure Payment', 'Fast Delivery', 'Customer Satisfaction'] as $promise)
                        <div class="border border-gold/15 px-5 py-4 text-sm uppercase tracking-[.14em] text-cream/85">{{ $promise }}</div>
                    @endforeach
                </div>

                <a class="luxury-link mt-10 inline-block" href="{{ route('collection') }}">Explore Collection</a>
            </div>
        </div>
    </section>
</x-layouts.store>
