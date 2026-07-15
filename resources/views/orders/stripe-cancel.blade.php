<x-layouts.store title="Stripe payment cancelled | Cherry Bellemont">
    <section class="mx-auto max-w-3xl px-6 py-20 text-center">
        <p class="uppercase tracking-[.25em] text-gold">Order {{ $order->order_number }}</p>
        <h1 class="mt-4 text-5xl">Card payment cancelled.</h1>
        <p class="mx-auto mt-6 max-w-xl text-xl text-cream/75">Your order remains reserved and payment has not been confirmed. You can safely try Stripe Checkout again.</p>
        @error('stripe')<p class="mt-6 border border-gold/40 p-4 text-gold">{{ $message }}</p>@enderror

        @if($order->payment_status !== 'paid' && $order->order_status !== 'cancelled')
            <form class="mt-8" method="POST" action="{{ route('stripe.retry', ['order' => $order->order_number, 'token' => $token]) }}">
                @csrf
                <button class="luxury-link" type="submit">Retry Stripe Payment</button>
            </form>
        @endif
        <a class="nav-link mt-8 inline-block" href="{{ route('collection') }}">Return to Collection</a>
    </section>
</x-layouts.store>
