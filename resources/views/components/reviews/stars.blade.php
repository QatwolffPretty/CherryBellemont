@props(['rating' => 0, 'label' => null, 'size' => 'text-base'])

@php($filled = max(0, min(5, (int) round((float) $rating))))

<span {{ $attributes->class('inline-flex items-center gap-0.5 text-gold '.$size) }} role="img" aria-label="{{ $label ?? number_format((float) $rating, 1).' out of 5 stars' }}">
    @for($star = 1; $star <= 5; $star++)
        <i class="bi {{ $star <= $filled ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
    @endfor
</span>
