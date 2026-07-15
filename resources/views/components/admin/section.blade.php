@props(['width' => '6xl'])

@php
    $widthClass = match ($width) {
        '2xl' => 'max-w-2xl',
        '7xl' => 'max-w-7xl',
        default => 'max-w-6xl',
    };
@endphp

<section {{ $attributes->class($widthClass.' mx-auto px-6 py-16') }}>{{ $slot }}</section>
