<x-layouts.store :title="$title.' | Cherry Bellemont'">
    <section class="mx-auto max-w-5xl px-6 py-16 md:py-24">
        <p class="uppercase tracking-[.25em] text-gold">Cherry Bellemont</p>
        <h1 class="mt-4 text-4xl md:text-5xl">{{ $heading }}</h1>
        <p class="mt-5 max-w-2xl leading-8 text-cream/70">For product, order, and general enquiries, our client care team is here to help.</p>

        <div class="mt-12 grid gap-10 border-t border-gold/30 pt-10 lg:grid-cols-2 lg:gap-16">
            <x-storefront.contact-details />
            <div class="border-t border-cream/15 pt-10 lg:border-l lg:border-t-0 lg:pl-16 lg:pt-0">
                <x-storefront.social-links />
                <p class="mt-10 leading-8 text-cream/70">When contacting us about an existing order, please include your order number so we can assist you more efficiently.</p>
            </div>
        </div>
    </section>
</x-layouts.store>
