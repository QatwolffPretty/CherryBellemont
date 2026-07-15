@props(['id', 'title'])

<div id="{{ $id }}" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="admin-modal-panel">
        <h2 id="{{ $id }}-title" class="text-2xl">{{ $title }}</h2>
        <div class="mt-5">{{ $slot }}</div>
        @isset($footer)
            <footer class="admin-card-footer">{{ $footer }}</footer>
        @endisset
    </div>
</div>
