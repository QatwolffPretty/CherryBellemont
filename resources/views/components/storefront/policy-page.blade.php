@props(['title', 'eyebrow' => 'Client care', 'intro' => null])

<x-layouts.store :title="$title.' | Cherry Bellemont'">
    <section class="mx-auto max-w-4xl px-6 py-16 md:py-24">
        <p class="uppercase tracking-[.25em] text-gold">{{ $eyebrow }}</p>
        <h1 class="mt-4 text-4xl leading-tight md:text-5xl">{{ $title }}</h1>
        @if($intro)
            <p class="mt-6 max-w-3xl leading-8 text-cream/75">{{ $intro }}</p>
        @endif

        <div class="mt-12 space-y-10 border-t border-gold/30 pt-10 leading-8 text-cream/80">
            {{ $slot }}
        </div>
    </section>
</x-layouts.store>
