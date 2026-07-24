@props(['status' => null, 'label' => null, 'tone' => null])

@php
    $normalized = strtolower((string) ($status ?? ''));
    $tone ??= match ($normalized) {
        'paid', 'approved', 'active', 'delivered', 'featured', 'in_stock' => 'positive',
        'processing', 'packed', 'shipped', 'in_transit', 'out_for_delivery', 'ready', 'payment_review', 'low_stock' => 'warning',
        'failed', 'rejected', 'cancelled', 'archived', 'out_of_stock', 'delivery_failed', 'returned' => 'negative',
        default => 'pending',
    };
    $display = $label ?? str($normalized ?: 'pending')->replace('_', ' ')->title();
@endphp

<span {{ $attributes->class('admin-badge admin-badge-'.$tone) }}>{{ $display }}</span>
