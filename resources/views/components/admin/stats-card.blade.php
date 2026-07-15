@props(['label', 'value', 'subtitle' => null, 'href' => null, 'accent' => false])

@php($classes = 'admin-card block '.($accent ? 'border-gold/40' : ''))

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        <p class="text-cream/65">{{ $label }}</p>
        <p class="mt-2 text-3xl text-gold">{{ $value }}</p>
        @if($subtitle)<p class="mt-1 text-xs text-cream/50">{{ $subtitle }}</p>@endif
    </a>
@else
    <section {{ $attributes->class($classes) }}>
        <p class="text-cream/65">{{ $label }}</p>
        <p class="mt-2 text-3xl text-gold">{{ $value }}</p>
        @if($subtitle)<p class="mt-1 text-xs text-cream/50">{{ $subtitle }}</p>@endif
    </section>
@endif
