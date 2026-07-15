@props(['title' => null])

<section {{ $attributes->class('admin-card') }}>
    @if($title)
        <h2 class="admin-card-title">{{ $title }}</h2>
    @endif

    {{ $slot }}

    @isset($footer)
        <footer class="admin-card-footer">{{ $footer }}</footer>
    @endisset
</section>
