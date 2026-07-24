@inject('settings', '\\App\\Services\\SettingsService')
<section aria-labelledby="contact-information-heading">
    <p class="uppercase tracking-[.2em] text-gold">Client care</p>
    <h2 id="contact-information-heading" class="mt-3 font-display text-3xl text-cream">Contact information</h2>

    <div class="mt-7 space-y-6">
        <div class="flex items-start gap-4">
            <i class="bi bi-headset mt-1 text-xl text-gold" aria-hidden="true"></i>
            <div>
                <p class="text-sm uppercase tracking-[.14em] text-cream/60">Customer Support Email</p>
                <a class="mt-1 inline-block text-cream transition-colors duration-200 hover:text-gold" href="mailto:{{ $settings->get('contact.support_email', config('store.support_email')) }}">{{ $settings->get('contact.support_email', config('store.support_email')) }}</a>
            </div>
        </div>

        <div class="flex items-start gap-4">
            <i class="bi bi-envelope-fill mt-1 text-xl text-gold" aria-hidden="true"></i>
            <div>
                <p class="text-sm uppercase tracking-[.14em] text-cream/60">General Enquiries</p>
                <a class="mt-1 inline-block text-cream transition-colors duration-200 hover:text-gold" href="mailto:{{ $settings->get('contact.general_email', config('store.general_email')) }}">{{ $settings->get('contact.general_email', config('store.general_email')) }}</a>
            </div>
        </div>

        <div class="flex items-start gap-4">
            <i class="bi bi-clock-fill mt-1 text-xl text-gold" aria-hidden="true"></i>
            <div>
                <p class="text-sm uppercase tracking-[.14em] text-cream/60">Business Hours</p>
                <dl class="mt-2 space-y-1 text-cream/80">
                    <div class="whitespace-pre-line">{{ $settings->get('store.business_hours') }}</div>
                </dl>
            </div>
        </div>
    </div>
</section>
