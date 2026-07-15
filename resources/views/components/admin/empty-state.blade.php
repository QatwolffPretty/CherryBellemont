@props(['title', 'description' => null, 'icon' => null])

<div {{ $attributes->class('admin-empty-state') }}>
    @if($icon)<i class="bi {{ $icon }} text-3xl text-gold" aria-hidden="true"></i>@endif
    <h2 class="mt-4 text-2xl text-cream">{{ $title }}</h2>
    @if($description)<p class="mt-3 text-sm leading-6">{{ $description }}</p>@endif
    {{ $slot }}
</div>
