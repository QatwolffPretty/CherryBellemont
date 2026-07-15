@props(['name', 'label' => null, 'help' => null, 'required' => false])

@php($inputId = $attributes->get('id', $name))

<div>
    @if($label)<label class="admin-label" for="{{ $inputId }}">{{ $label }}</label>@endif
    <select id="{{ $inputId }}" name="{{ $name }}" @required($required) {{ $attributes->except('id')->class('admin-field') }}>{{ $slot }}</select>
    @if($help)<p class="mt-2 text-sm text-cream/60">{{ $help }}</p>@endif
    @error($name)<x-admin.validation-error :message="$message" />@enderror
</div>
