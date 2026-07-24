<x-layouts.store :title="'Track '.($order->order_number ?? $order->number).' | Cherry Bellemont'">
    @php($shipment = $order->latestShipment)
    <section class="mx-auto max-w-4xl px-6 py-16">
        <p class="uppercase tracking-[.25em] text-gold">Shipment Tracking</p>
        <h1 class="mt-3 text-4xl">{{ $order->order_number ?? $order->number }}</h1>
        @if($shipment)
            <section class="mt-8 border border-cream/15 p-6"><div class="grid gap-5 md:grid-cols-2"><div><p class="text-cream/60">Courier</p><p class="mt-1 text-xl text-gold">{{ $shipment->courier_name_snapshot ?: 'Awaiting courier details' }}</p></div><div><p class="text-cream/60">Shipment Status</p><p class="mt-1 text-xl capitalize text-gold">{{ str($shipment->shipment_status)->replace('_', ' ') }}</p></div><div><p class="text-cream/60">Tracking Number</p><p class="mt-1">{{ $shipment->tracking_number ?: 'To be confirmed' }}</p></div><div><p class="text-cream/60">Estimated Delivery</p><p class="mt-1">{{ $shipment->estimated_delivery_at?->format('d M Y') ?: 'To be confirmed' }}</p></div></div>@if($shipment->tracking_url)<a class="luxury-link mt-6 inline-block" href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Track with Courier</a>@endif</section>
            <section class="mt-6 border border-cream/15 p-6"><h2 class="text-2xl">Shipment timeline</h2><ol class="mt-5 space-y-4 border-l border-gold/50 pl-5">@forelse($shipment->events as $event)<li><p class="text-gold">{{ $event->title }}</p><p class="mt-1 text-sm text-cream/60">{{ $event->event_time?->format('d M Y H:i') }}{{ $event->location ? ' · '.$event->location : '' }}</p>@if($event->description)<p class="mt-2 text-sm text-cream/75">{{ $event->description }}</p>@endif</li>@empty<li class="text-cream/60">Tracking updates will appear here.</li>@endforelse</ol></section>
        @else
            <p class="mt-8 border border-gold/40 p-5 text-cream/75">Shipment details will appear here once your order is dispatched.</p>
        @endif
        <a class="luxury-link mt-8 inline-block" href="{{ route('orders.guest.show', ['order' => $order->order_number ?? $order->number, 'token' => $token]) }}">View Secure Order</a>
    </section>
</x-layouts.store>
