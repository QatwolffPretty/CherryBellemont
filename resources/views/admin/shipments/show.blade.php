<x-layouts.admin :title="$shipment->shipment_number.' | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Fulfilment" :title="$shipment->shipment_number" :subtitle="'Order '.($shipment->order?->order_number ?? $shipment->order?->number)">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.orders.show', $shipment->order)">View Order</x-admin.button>
                @if($shipment->label_path)
                    <x-admin.button variant="outline" icon="bi-file-earmark-arrow-down" :href="route('admin.shipments.label.download', $shipment)">Download Label</x-admin.button>
                @endif
            </x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        <div class="mt-8 grid gap-8 xl:grid-cols-[1fr_24rem]">
            <div class="space-y-6">
                <x-admin.card title="Shipment timeline">
                    <ol class="mt-5 space-y-5 border-l border-gold/45 pl-5">
                        @forelse($shipment->events as $event)
                            <li>
                                <p class="text-gold">{{ $event->title }} <span class="ml-2 text-xs text-cream/55">{{ str($event->status)->replace('_', ' ')->title() }}</span></p>
                                <p class="mt-1 text-sm text-cream/65">{{ $event->event_time?->format('d M Y H:i') }}{{ $event->location ? ' · '.$event->location : '' }}</p>
                                @if($event->description)<p class="mt-2 whitespace-pre-line text-sm text-cream/80">{{ $event->description }}</p>@endif
                            </li>
                        @empty
                            <li class="text-cream/60">No tracking events yet.</li>
                        @endforelse
                    </ol>
                </x-admin.card>

                <x-admin.card title="Items to dispatch">
                    <x-admin.table class="mt-5">
                        <x-slot:head><tr><th>Product</th><th>Quantity</th></tr></x-slot:head>
                        @foreach($shipment->order->items as $item)
                            <tr><td>{{ $item->product_name ?? $item->name }}</td><td>{{ $item->quantity }}</td></tr>
                        @endforeach
                    </x-admin.table>
                </x-admin.card>

                <x-admin.card title="Shipment audit">
                    <x-admin.table class="mt-5">
                        <x-slot:head><tr><th>Action</th><th>Admin</th><th>Date</th></tr></x-slot:head>
                        @forelse($shipment->auditLogs as $audit)
                            <tr><td>{{ str($audit->action)->replace('_', ' ')->title() }}</td><td>{{ $audit->admin?->name ?: 'System' }}</td><td>{{ $audit->created_at?->format('d M Y H:i') }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-cream/60">No audit entries.</td></tr>
                        @endforelse
                    </x-admin.table>
                </x-admin.card>
            </div>

            <div class="space-y-6">
                <x-admin.card title="Shipment details">
                    <dl class="mt-5 space-y-3">
                        <div><dt class="text-sm text-cream/60">Status</dt><dd><x-admin.badge :status="$shipment->shipment_status" /></dd></div>
                        <div><dt class="text-sm text-cream/60">Courier</dt><dd>{{ $shipment->courier_name_snapshot ?: 'Not selected' }}</dd></div>
                        <div><dt class="text-sm text-cream/60">Service</dt><dd>{{ $shipment->service_name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-cream/60">Tracking</dt><dd>{{ $shipment->tracking_number ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-cream/60">Estimated delivery</dt><dd>{{ $shipment->estimated_delivery_at?->format('d M Y') ?: '—' }}</dd></div>
                        @if($shipment->admin_note)<div><dt class="text-sm text-cream/60">Admin note</dt><dd class="whitespace-pre-line">{{ $shipment->admin_note }}</dd></div>@endif
                    </dl>
                </x-admin.card>

                @if(! in_array($shipment->shipment_status, ['shipped','in_transit','out_for_delivery','delivered','returned','cancelled'], true))
                    <x-admin.card title="Confirm dispatch">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.shipments.ship', $shipment) }}" class="mt-5 space-y-4">
                            @csrf
                            <x-admin.select name="courier_id" label="Courier" required>
                                <option value="">Select courier</option>
                                @foreach($couriers as $courier)<option value="{{ $courier->id }}" @selected(old('courier_id', $shipment->courier_id) == $courier->id)>{{ $courier->name }}</option>@endforeach
                            </x-admin.select>
                            <x-admin.form-input name="service_name" label="Service name" :value="$shipment->service_name" />
                            <x-admin.form-input name="tracking_number" label="Tracking number" :value="$shipment->tracking_number" required />
                            <x-admin.form-input name="estimated_delivery_at" type="date" label="Estimated delivery" :value="$shipment->estimated_delivery_at?->format('Y-m-d')" />
                            <x-admin.form-input name="label" type="file" label="Replace private label" accept=".pdf,.png,.jpg,.jpeg" />
                            <x-admin.textarea name="admin_note" label="Admin note" :value="$shipment->admin_note" />
                            <x-admin.button type="submit" icon="bi-send-check">Mark as Shipped</x-admin.button>
                        </form>
                    </x-admin.card>
                @endif

                @if(! in_array($shipment->shipment_status, ['delivered','returned','cancelled'], true))
                    <x-admin.card title="Add tracking event">
                        <form method="POST" action="{{ route('admin.shipments.events.store', $shipment) }}" class="mt-5 space-y-4">
                            @csrf
                            <x-admin.select name="status" label="Event status" required>
                                @foreach(['in_transit','out_for_delivery','delivered','delivery_failed','returned','cancelled'] as $status)<option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach
                            </x-admin.select>
                            <x-admin.form-input name="title" label="Event title" required />
                            <x-admin.textarea name="description" label="Description" />
                            <x-admin.form-input name="location" label="Location" />
                            <x-admin.form-input name="event_time" type="datetime-local" label="Event date and time" :value="now()->format('Y-m-d\\TH:i')" required />
                            <x-admin.button type="submit" variant="secondary" icon="bi-plus-lg">Add Event</x-admin.button>
                        </form>
                    </x-admin.card>
                @endif
            </div>
        </div>
    </x-admin.section>
</x-layouts.admin>
