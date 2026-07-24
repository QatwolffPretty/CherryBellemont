<x-layouts.store title="DuitNow payment | Cherry Bellemont">
    <section class="mx-auto max-w-5xl px-6 py-16">
        <p class="uppercase tracking-[.25em] text-gold">DuitNow payment</p>
        <h1 class="mt-3 text-5xl">Complete your transfer</h1>

        <div class="mt-8 grid gap-8 md:grid-cols-2">
            <div class="border border-gold/30 p-6">
                <p>Order <span class="text-gold">{{ $order->order_number }}</span></p>
                <p class="mt-2">{{ $order->customer_name }}</p>
                <p>Subtotal RM {{ number_format($order->subtotal, 2) }}</p>
                <p>{{ $order->shipping_method_name }} · RM {{ number_format($order->shipping_fee, 2) }}</p>
                @if($order->gift_wrapping)
                    <p class="mt-2 text-gold">Signature Gift Experience · RM {{ number_format($order->gift_wrapping_fee, 2) }}</p>
                    @if($order->gift_message)<p class="mt-2 text-sm text-cream/65">Gift message: {{ $order->gift_message }}</p>@endif
                @endif
                <p class="mt-4 text-2xl text-gold">Total RM {{ number_format($order->total, 2) }}</p>
                <p class="mt-4 capitalize">Payment: {{ $order->payment_status }}</p>
                @if(config('duitnow.qr_path'))<img class="mt-6 max-h-72 w-full object-contain" src="{{ asset(config('duitnow.qr_path')) }}" alt="DuitNow QR">@endif
            </div>

            <div>
                <h2 class="text-2xl">Payment instructions</h2>
                <p class="mt-4 whitespace-pre-line text-cream/75">{{ config('duitnow.payment_instructions') }}</p>
                <p class="mt-4">{{ config('duitnow.account_name') }} {{ config('duitnow.id') ? '· '.config('duitnow.id') : '' }}</p>
                @php($latest = $order->paymentReceipts()->latest()->first())
                @if($order->payment_status === 'paid')
                    <p class="mt-6 text-gold">Payment approved.</p>
                @elseif($latest?->status === 'pending')
                    <p class="mt-6 text-gold">Receipt pending review.</p>
                @else
                    @if($latest?->status === 'rejected')<p class="mt-6 text-gold">Receipt rejected: {{ $latest->rejection_reason }}</p>@endif
                    <form class="mt-6 space-y-3" method="POST" enctype="multipart/form-data" action="{{ route('orders.payment-receipts.store', ['order' => $order->order_number, 'token' => $token]) }}">
                        @csrf
                        <input class="field" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                        @error('receipt')<p class="text-gold">{{ $message }}</p>@enderror
                        <button class="luxury-link" type="submit">Upload receipt</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
</x-layouts.store>
