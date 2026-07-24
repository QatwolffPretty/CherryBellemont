<x-layouts.admin :title="($courier->exists ? 'Edit Courier' : 'Add Courier').' | Cherry Bellemont'">
    <x-admin.section width="4xl">
        <x-admin.page-header eyebrow="Shipment management" :title="$courier->exists ? 'Edit Courier' : 'Add Courier'" subtitle="Tracking URL templates use {tracking_number}; they never execute code.">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.couriers.index')">Back to Couriers</x-admin.button></x-slot:actions>
        </x-admin.page-header>
        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        <x-admin.card class="mt-8">
            <form method="POST" enctype="multipart/form-data" action="{{ $courier->exists ? route('admin.couriers.update', $courier) : route('admin.couriers.store') }}" class="grid gap-5 md:grid-cols-2">
                @csrf @if($courier->exists) @method('PUT') @endif
                <x-admin.form-input name="name" label="Courier name" :value="$courier->name" required />
                <x-admin.form-input name="code" label="Courier code" :value="$courier->code" required help="Uppercase letters, numbers, hyphens, and underscores only." />
                <x-admin.form-input class="md:col-span-2" name="tracking_url_template" label="Tracking URL template" :value="$courier->tracking_url_template" help="Example: https://courier.example/track/{tracking_number}" />
                <x-admin.form-input name="website_url" type="url" label="Website URL" :value="$courier->website_url" />
                <x-admin.form-input name="sort_order" type="number" label="Display order" :value="$courier->sort_order ?? 0" required />
                <x-admin.form-input class="md:col-span-2" name="logo" type="file" label="Courier logo" accept=".jpg,.jpeg,.png,.webp" help="Optional public logo, maximum 5 MB." />
                @if($courier->logo_path)<div class="md:col-span-2"><img class="h-16 w-16 border border-cream/20 object-contain" src="{{ asset('storage/'.$courier->logo_path) }}" alt="Current courier logo"></div>@endif
                <input type="hidden" name="is_active" value="0">
                <label class="md:col-span-2 inline-flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $courier->exists ? $courier->is_active : true))> Active for new shipments</label>
                <div class="md:col-span-2"><x-admin.button type="submit">Save Courier</x-admin.button></div>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
