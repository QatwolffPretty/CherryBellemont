<x-layouts.admin title="Shipping zone">
    <x-admin.section width="2xl">
        <x-admin.page-header :title="$zone->exists ? 'Edit shipping zone' : 'Add shipping zone'" />

        <x-admin.card class="mt-8">
            <form class="space-y-4" method="POST" action="{{ $zone->exists ? route('admin.shipping-zones.update', $zone) : route('admin.shipping-zones.store') }}">
                @csrf
                @if($zone->exists)@method('PUT')@endif
                <x-admin.form-input name="name" label="Name" :value="$zone->name" />
                <x-admin.form-input name="state" label="State" :value="$zone->state" />
                <x-admin.form-input name="city_or_area" label="City/area" :value="$zone->city_or_area" />
                <x-admin.form-input name="postcode_from" label="Postcode from" :value="$zone->postcode_from" />
                <x-admin.form-input name="postcode_to" label="Postcode to" :value="$zone->postcode_to" />
                <x-admin.form-input name="base_fee" label="Base fee" type="number" step="0.01" :value="$zone->base_fee" />
                <x-admin.form-input name="sort_order" label="Sort order" type="number" step="0.01" :value="$zone->sort_order" />
                <x-admin.checkbox name="is_active" label="Active" :checked="old('is_active', $zone->is_active ?? true)" />
                <x-admin.button type="submit">Save</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
