<x-layouts.store title="Stripe payment | Cherry Bellemont">
    <section class="mx-auto max-w-3xl px-6 py-20 text-center">
        <p class="uppercase tracking-[.25em] text-gold">Order {{ $order->order_number }}</p>
        <h1 class="mt-4 text-5xl">Payment return received.</h1>
        <p class="mt-6 text-xl text-cream/75">Thank you, {{ $order->customer_name }}.</p>

        <div class="mt-10 border border-cream/15 p-6 text-left">
            <p class="flex justify-between gap-5"><span>Order total</span><span class="text-gold">RM {{ number_format($order->total, 2) }}</span></p>
            <p class="mt-4 flex justify-between gap-5"><span>Stripe payment</span><span class="capitalize text-gold">{{ $session->payment_status ?? 'processing' }}</span></p>
            <p class="mt-4 flex justify-between gap-5"><span>Order status</span><span class="capitalize text-gold">{{ str($order->order_status)->replace('_', ' ') }}</span></p>
        </div>

        @if($order->payment_status === 'paid')
            <p class="mt-8 border border-gold/40 p-4 text-gold">Payment Approved</p>
        @else
            <p class="mt-8 border border-gold/40 p-4 text-gold">Payment confirmation is processing. Your order will update after Stripe sends its verified confirmation.</p>
        @endif

        <a class="luxury-link mt-8 inline-block" href="{{ route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]) }}">View secure order</a>
    </section>
</x-layouts.store>
