<x-layouts.admin :title="$title.' | Cherry Bellemont'">
    <x-admin.section width="xl"><x-admin.page-header eyebrow="Catalogue" :title="$title" />
        <x-admin.card class="mt-8"><form class="space-y-6" method="POST" action="{{ $route }}">@csrf @if($item->exists) @method('PUT') @endif
            <x-admin.form-input name="name" label="Name" :value="$item->name" required />
            @if($type === 'size')<x-admin.form-input name="code" label="Code" :value="$item->code" help="For example XS, S, M, L or XL." />@else<x-admin.form-input name="slug" label="URL slug" :value="$item->slug" help="Leave blank to generate it from the name." />@endif
            @if($type === 'colour')<x-admin.form-input name="hex_code" label="Hex colour" :value="$item->hex_code" placeholder="#5B1E2D" help="Optional six-digit hex value for storefront swatches." />@endif
            <x-admin.form-input name="sort_order" label="Display order" type="number" min="0" :value="$item->sort_order ?? 0" />
            <x-admin.checkbox name="is_active" label="Available for storefront filters" :checked="old('is_active', $item->exists ? $item->is_active : true)" />
            <x-admin.button type="submit">Save {{ $type === 'tag' ? 'collection tag' : $type }}</x-admin.button>
        </form></x-admin.card>
    </x-admin.section>
</x-layouts.admin>
