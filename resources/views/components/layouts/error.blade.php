@props([
    'status',
    'title',
    'message',
])

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('store.company_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-wine text-cream antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-16 text-center">
        <div class="w-full border-y border-gold/40 py-12">
            <p class="font-display text-6xl text-gold">{{ $status }}</p>
            <p class="mt-5 uppercase tracking-[.24em] text-gold">Cherry Bellemont</p>
            <h1 class="mt-4 text-4xl">{{ $title }}</h1>
            <p class="mx-auto mt-5 max-w-xl leading-8 text-cream/75">{{ $message }}</p>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a class="luxury-link" href="{{ route('home') }}">Return Home</a>
                <a class="nav-link self-center" href="{{ route('collection') }}">Explore Collection</a>
            </div>
        </div>
    </main>
</body>
</html>
