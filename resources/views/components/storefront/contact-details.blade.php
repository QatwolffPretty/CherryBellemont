<section aria-labelledby="contact-information-heading">
    <p class="uppercase tracking-[.2em] text-gold">Client care</p>
    <h2 id="contact-information-heading" class="mt-3 font-display text-3xl text-cream">Contact information</h2>

    <div class="mt-7 space-y-6">
        <div class="flex items-start gap-4">
            <i class="bi bi-headset mt-1 text-xl text-gold" aria-hidden="true"></i>
            <div>
                <p class="text-sm uppercase tracking-[.14em] text-cream/60">Customer Support Email</p>
                <a class="mt-1 inline-block text-cream transition-colors duration-200 hover:text-gold" href="mailto:{{ config('store.support_email') }}">{{ config('store.support_email') }}</a>
            </div>
        </div>

        <div class="flex items-start gap-4">
            <i class="bi bi-envelope-fill mt-1 text-xl text-gold" aria-hidden="true"></i>
            <div>
                <p class="text-sm uppercase tracking-[.14em] text-cream/60">General Enquiries</p>
                <a class="mt-1 inline-block text-cream transition-colors duration-200 hover:text-gold" href="mailto:{{ config('store.general_email') }}">{{ config('store.general_email') }}</a>
            </div>
        </div>

        <div class="flex items-start gap-4">
            <i class="bi bi-clock-fill mt-1 text-xl text-gold" aria-hidden="true"></i>
            <div>
                <p class="text-sm uppercase tracking-[.14em] text-cream/60">Business Hours</p>
                <dl class="mt-2 space-y-1 text-cream/80">
                    @foreach(config('store.business_days') as $key => $day)
                        <div class="grid gap-x-5 sm:grid-cols-[12rem_1fr]">
                            <dt>{{ $day }}</dt>
                            <dd>{{ config("store.business_hours.{$key}") }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </div>
</section>
