<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cherry Bellemont' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-wine text-cream antialiased">
    <header class="border-b border-cream/15 bg-wine-deep/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-6">
            <a href="{{ route('home') }}" class="font-display text-xl tracking-[.2em] text-cream sm:text-2xl">CHERRY BELLEMONT</a>

            <div class="flex items-center gap-5 text-sm">
                <a class="nav-link" href="{{ route('home') }}">Home</a>
                <a class="nav-link" href="{{ route('collection') }}">Collection</a>
                <a class="nav-link" href="{{ route('about') }}">About</a>

                <div class="flex items-center gap-[18px]">
                    <a class="nav-icon relative" href="{{ route('cart.index') }}" aria-label="Shopping Bag" data-bs-toggle="tooltip" data-bs-title="Shopping Bag">
                        <i class="bi bi-handbag-fill" aria-hidden="true"></i>
                        <span class="cart-count" aria-live="polite">{{ array_sum(session('cart', [])) }}</span>
                    </a>
                    @auth
                        <a class="nav-icon" href="{{ auth()->user()->is_admin ? route('admin.dashboard') : route('dashboard') }}" aria-label="Account" data-bs-toggle="tooltip" data-bs-title="Account">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                        </a>
                    @else
                        <a class="nav-icon" href="{{ route('login') }}" aria-label="Account" data-bs-toggle="tooltip" data-bs-title="Account">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                        </a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <main>{{ $slot }}</main>

    <x-newsletter.subscribe-form source="newsletter_section" />

    <footer class="border-t border-cream/15 bg-wine-deep px-6 py-12 text-center text-sm tracking-wider text-cream/60">
        <div class="mx-auto max-w-7xl">
            <div>
                <p class="font-display text-lg tracking-[.18em] text-cream">CHERRY BELLEMONT</p>
                <nav class="mt-5 flex flex-wrap justify-center gap-x-5 gap-y-3" aria-label="Footer navigation">
                    <a class="nav-link" href="{{ route('faq.index') }}">FAQ</a>
                    <a class="nav-link" href="{{ route('contact') }}">Contact</a>
                    <a class="nav-link" href="{{ route('shipping.policy') }}">Shipping</a>
                    <a class="nav-link" href="{{ route('refund.policy') }}">Refund &amp; Returns</a>
                    <a class="nav-link" href="{{ route('privacy.policy') }}">Privacy</a>
                    <a class="nav-link" href="{{ route('terms.policy') }}">Terms &amp; Conditions</a>
                </nav>
                <x-storefront.social-links centered heading-id="footer-social-links-heading" class="mt-8" />
                <p class="mt-8">&copy; {{ date('Y') }} Cherry Bellemont <span class="mx-2 text-gold">&mdash;</span> All Rights Reserved</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new bootstrap.Tooltip(element));</script>
</body>
</html>
