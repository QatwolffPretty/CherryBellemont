<x-layouts.admin :title="'Create Shipment | '.($order->order_number ?? $order->number)">
    <x-admin.section width="4xl">
        <x-admin.page-header eyebrow="Fulfilment" title="Create Shipment" :subtitle="'Order '.($order->order_number ?? $order->number).' must remain paid and packed before dispatch.'">
            <x-slot:actions><x-admin.button variant="outline" :href="route('admin.orders.show', $order)">Back to Order</x-admin.button></x-slot:actions>
        </x-admin.page-header>
        <x-admin.card class="mt-8">
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.orders.shipments.store', $order) }}" class="grid gap-5 md:grid-cols-2">
                @csrf
                <x-admin.select name="courier_id" label="Courier"><option value="">Select later</option>@foreach($couriers as $courier)<option value="{{ $courier->id }}" @selected(old('courier_id', $defaultCourierId) == $courier->id)>{{ $courier->name }}</option>@endforeach</x-admin.select>
                <x-admin.form-input name="service_name" label="Service name" :value="old('service_name')" />
                <x-admin.form-input name="tracking_number" label="Tracking number" :value="old('tracking_number')" help="Enter a courier-issued tracking number manually." />
                <x-admin.form-input name="estimated_delivery_at" type="date" label="Estimated delivery" :value="old('estimated_delivery_at', $defaultEstimatedDelivery)" />
                <x-admin.form-input class="md:col-span-2" name="label" type="file" label="Private shipping label" accept=".pdf,.png,.jpg,.jpeg" help="PDF, PNG, JPG, or JPEG; maximum 10 MB. Only admins can download it." />
                <x-admin.textarea class="md:col-span-2" name="admin_note" label="Admin note" :value="old('admin_note')" />
                <div class="md:col-span-2"><x-admin.button type="submit" icon="bi-box-seam">Create Shipment</x-admin.button></div>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
