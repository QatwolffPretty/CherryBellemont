<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cherry Bellemont' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-wine text-cream antialiased">
    <header class="border-b border-cream/15 bg-wine-deep/90">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-6">
            <a href="{{ route('home') }}" class="font-display text-xl tracking-[.2em] text-cream sm:text-2xl">
                CHERRY BELLEMONT
            </a>

            <div class="flex items-center gap-4 text-sm sm:gap-6">
                <a class="nav-link" href="{{ route('collection') }}">Collection</a>
                <a class="nav-link" href="{{ route('cart.index') }}">Bag ({{ array_sum(session('cart', [])) }})</a>

                @auth
                    @if(auth()->user()->is_admin)
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">Atelier</a>
                    @endif
                    <a class="nav-link" href="{{ route('dashboard') }}">Account</a>
                    <a class="nav-link" href="{{ route('orders.index') }}">Orders</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-link">Sign out</button>
                    </form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Sign in</a>
                    <a class="nav-link" href="{{ route('register') }}">Create account</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-cream/15 bg-wine-deep px-6 py-10 text-center text-sm tracking-wider text-cream/60">
        &copy; {{ date('Y') }} Cherry Bellemont <span class="mx-2 text-gold">&mdash;</span> All Rights Reserved
    </footer>
</body>
</html>
