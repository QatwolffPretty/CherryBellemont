<x-layouts.admin title="Delivery method">
    <x-admin.section width="2xl">
        <x-admin.page-header :title="$method->exists ? 'Edit delivery method' : 'Add delivery method'" />

        <x-admin.card class="mt-8">
            <form class="space-y-4" method="POST" action="{{ $method->exists ? route('admin.delivery-methods.update', $method) : route('admin.delivery-methods.store') }}">
                @csrf
                @if($method->exists)@method('PUT')@endif
                <x-admin.form-input name="name" label="Name" :value="$method->name" />
                <x-admin.form-input name="code" label="Code" :value="$method->code" />
                <x-admin.form-input name="description" label="Description" :value="$method->description" />
                <x-admin.form-input name="additional_fee" label="Additional fee" :value="$method->additional_fee" />
                <x-admin.form-input name="estimated_days" label="Estimated days" :value="$method->estimated_days" />
                <x-admin.form-input name="sort_order" label="Sort order" :value="$method->sort_order" />
                <x-admin.checkbox name="is_pickup" label="Self pickup" :checked="old('is_pickup', $method->is_pickup)" />
                <x-admin.checkbox name="is_active" label="Active" :checked="old('is_active', $method->is_active ?? true)" />
                <x-admin.button type="submit">Save</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
