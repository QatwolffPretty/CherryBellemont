<x-layouts.store title="FAQ | Cherry Bellemont">
    <section class="mx-auto max-w-5xl px-6 py-20">
        <p class="uppercase tracking-[.25em] text-gold">Client care</p>
        <h1 class="mt-3 text-5xl">Frequently asked questions</h1>
        <p class="mt-5 max-w-2xl text-cream/70">Useful guidance for ordering, payment, delivery, and caring for your Cherry Bellemont pieces.</p>

        @forelse($faqsByCategory as $category => $faqs)
            <section class="mt-14">
                <h2 class="text-3xl text-gold">{{ $category }}</h2>
                <div class="mt-5 border-t border-cream/15">
                    @foreach($faqs as $faq)
                        <details class="border-b border-cream/15 py-5">
                            <summary class="cursor-pointer pr-6 text-xl text-cream marker:text-gold">{{ $faq->question }}</summary>
                            <div class="mt-4 max-w-3xl leading-7 text-cream/75">{!! app(\App\Services\FaqSanitizer::class)->sanitize($faq->answer) !!}</div>
                        </details>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="mt-12 border border-cream/15 p-10 text-center"><h2 class="text-2xl">FAQ coming soon</h2><p class="mt-3 text-cream/65">Our client care guidance is being prepared.</p></section>
        @endforelse
    </section>
</x-layouts.store>
