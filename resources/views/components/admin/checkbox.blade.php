@props(['name', 'label', 'checked' => false, 'value' => '1'])

<label {{ $attributes->class('flex items-center gap-3 text-sm text-cream/85') }}>
    <input class="admin-check" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))>
    <span>{{ $label }}</span>
</label>
@error($name)<x-admin.validation-error :message="$message" />@enderror
