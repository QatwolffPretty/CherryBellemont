@props(['heading' => 'Follow Cherry Bellemont', 'centered' => false, 'headingId' => 'social-links-heading', 'iconOnly' => false])
@inject('settings', '\\App\\Services\\SettingsService')

<section {{ $attributes->class($centered ? 'text-center' : '') }} aria-labelledby="{{ $headingId }}">
    <p id="{{ $headingId }}" class="uppercase tracking-[.2em] text-gold">{{ $heading }}</p>
    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-3 {{ $centered ? 'justify-center' : '' }}">
        <a class="inline-flex items-center gap-2 text-cream/80 transition-colors duration-200 hover:text-gold" href="{{ $settings->get('social.threads_url', config('store.threads_url')) }}" target="_blank" rel="noopener noreferrer" aria-label="Follow Cherry Bellemont on Threads">
            <i class="bi bi-threads text-lg" aria-hidden="true"></i>@unless($iconOnly)<span>Threads</span>@endunless
        </a>
        <a class="inline-flex items-center gap-2 text-cream/80 transition-colors duration-200 hover:text-gold" href="{{ $settings->get('social.instagram_url', config('store.instagram_url')) }}" target="_blank" rel="noopener noreferrer" aria-label="Follow Cherry Bellemont on Instagram">
            <i class="bi bi-instagram text-lg" aria-hidden="true"></i>@unless($iconOnly)<span>Instagram</span>@endunless
        </a>
        <a class="inline-flex items-center gap-2 text-cream/80 transition-colors duration-200 hover:text-gold" href="{{ $settings->get('social.facebook_url', config('store.facebook_url')) }}" target="_blank" rel="noopener noreferrer" aria-label="Follow Cherry Bellemont on Facebook">
            <i class="bi bi-facebook text-lg" aria-hidden="true"></i>@unless($iconOnly)<span>Facebook Page</span>@endunless
        </a>
    </div>
</section>
