@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])

@php($inputId = $attributes->get('id', $name))

<div>
    @if($label)<label class="admin-label" for="{{ $inputId }}">{{ $label }}</label>@endif
    @if($type === 'file')
        <input id="{{ $inputId }}" name="{{ $name }}" type="file" @required($required) {{ $attributes->except('id')->class('admin-field') }}>
    @else
        <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes->except('id')->class('admin-field') }}>
    @endif
    @if($help)<p class="mt-2 text-sm text-cream/60">{{ $help }}</p>@endif
    @error($name)<x-admin.validation-error :message="$message" />@enderror
</div>
