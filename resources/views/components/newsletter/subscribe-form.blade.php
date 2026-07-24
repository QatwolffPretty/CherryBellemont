@props(['source' => 'newsletter_section'])
@inject('settings', '\\App\\Services\\SettingsService')

<section {{ $attributes->class('newsletter-feature') }} data-newsletter-feature aria-labelledby="newsletter-heading">
    <img class="newsletter-feature-monogram" src="{{ asset('images/Cherry Red No BG.png') }}" alt="" aria-hidden="true">
    <div class="newsletter-feature-inner">
        <div class="newsletter-feature-copy">
            <p class="newsletter-feature-eyebrow">{{ $settings->get('newsletter.eyebrow', 'Exclusive Access') }}</p>
            <h2 id="newsletter-heading" class="newsletter-feature-heading">{{ $settings->get('newsletter.heading', 'Join the Cherry Bellemont Community') }}</h2>
            <p class="newsletter-feature-description">{{ $settings->get('newsletter.description', 'Be the first to discover new arrivals, exclusive collections, private promotions, styling inspiration, and exclusive Cherry Bellemont updates delivered directly to your inbox.') }}</p>
        </div>

        @if(session('newsletter_success'))
            <p class="newsletter-feature-status">{{ session('newsletter_success') }}</p>
        @endif

        <form class="newsletter-feature-form" method="POST" action="{{ route('newsletter.subscribe') }}">
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">
            <div>
                <label class="newsletter-feature-label" for="newsletter-name">Optional Name</label>
                <input id="newsletter-name" class="newsletter-feature-field" type="text" name="name" value="{{ old('name') }}" maxlength="160" autocomplete="name" placeholder="Your name">
                @error('name', 'newsletter')<p class="newsletter-feature-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="newsletter-feature-label" for="newsletter-email">Email Address</label>
                <input id="newsletter-email" class="newsletter-feature-field" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" placeholder="you@example.com" required>
                @error('email', 'newsletter')<p class="newsletter-feature-error">{{ $message }}</p>@enderror
            </div>
            <button class="newsletter-feature-button" type="submit">Subscribe</button>
        </form>
    </div>
</section>
