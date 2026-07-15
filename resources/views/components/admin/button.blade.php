@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'icon' => null])

@php
    $variants = [
        'primary' => 'admin-button-primary',
        'secondary' => 'admin-button-secondary',
        'outline' => 'admin-button-outline',
        'danger' => 'admin-button-danger',
        'success' => 'admin-button-success',
        'warning' => 'admin-button-warning',
        'icon' => 'admin-button-icon',
    ];
    $classes = 'admin-button '.($variants[$variant] ?? $variants['primary']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </button>
@endif
