@props(['status' => null, 'label' => null, 'tone' => null])

@php
    $normalized = strtolower((string) ($status ?? ''));
    $tone ??= match ($normalized) {
        'paid', 'approved', 'active', 'delivered', 'featured', 'in_stock' => 'positive',
        'processing', 'packed', 'shipped', 'payment_review', 'low_stock' => 'warning',
        'failed', 'rejected', 'cancelled', 'archived', 'out_of_stock' => 'negative',
        default => 'pending',
    };
    $display = $label ?? str($normalized ?: 'pending')->replace('_', ' ')->title();
@endphp

<span {{ $attributes->class('admin-badge admin-badge-'.$tone) }}>{{ $display }}</span>
