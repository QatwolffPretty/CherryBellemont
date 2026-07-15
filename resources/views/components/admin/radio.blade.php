@props(['name', 'label', 'value', 'checked' => false])

<label {{ $attributes->class('flex items-center gap-3 text-sm text-cream/85') }}>
    <input class="admin-check" type="radio" name="{{ $name }}" value="{{ $value }}" @checked((string) old($name) === (string) $value || (! old($name) && $checked))>
    <span>{{ $label }}</span>
</label>
@error($name)<x-admin.validation-error :message="$message" />@enderror
