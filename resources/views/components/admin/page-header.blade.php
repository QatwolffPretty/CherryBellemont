@props(['title', 'eyebrow' => null, 'subtitle' => null])

<header {{ $attributes->class('flex flex-wrap items-end justify-between gap-6') }}>
    <div>
        @isset($breadcrumb)
            <div class="mb-4 text-sm">{{ $breadcrumb }}</div>
        @endisset
        @if($eyebrow)
            <p class="uppercase tracking-[.25em] text-gold">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-3 text-4xl">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-4 max-w-3xl text-cream/70">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset
</header>
