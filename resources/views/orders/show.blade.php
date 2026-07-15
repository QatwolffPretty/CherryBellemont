<x-layouts.store :title="($order->order_number ?? $order->number).' | Cherry Bellemont'">
    @php
        $latestReceipt = $order->paymentReceipts->sortByDesc('created_at')->first();
        $canUploadReceipt = isset($token) && $order->payment_status === 'pending' && (! $latestReceipt || $latestReceipt->status !== 'pending');
        $timeline = [
            ['Order Placed', $order->created_at],
            ['Payment Submitted', $latestReceipt?->submitted_at],
            ['Payment Approved', $order->payment_status === 'paid' ? $latestReceipt?->reviewed_at : null],
            ['Processing', in_array($order->order_status, ['processing', 'packed', 'shipped', 'delivered'], true) ? $order->updated_at : null],
            ['Packed', in_array($order->order_status, ['packed', 'shipped', 'delivered'], true) ? $order->updated_at : null],
            ['Shipped', $order->shipped_at],
            ['Delivered', $order->delivered_at],
        ];
    @endphp

    <section class="mx-auto max-w-6xl px-6 py-16">
        <p class="uppercase tracking-[.25em] text-gold">Order</p>
        <h1 class="mt-3 text-4xl">{{ $order->order_number ?? $order->number }}</h1>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Customer information</h2>
                    <p class="mt-4">{{ $order->customer_name }}<br>{{ $order->customer_email }}<br>{{ $order->customer_phone }}</p>
                </section>

                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Order items</h2>
                    @foreach($order->items as $item)
                        <div class="mt-5 flex gap-4 border-b border-cream/15 pb-4">
                            @if($item->product && $item->product->image_path)
                                <img class="h-20 w-16 object-cover" src="{{ asset('storage/'.$item->product->image_path) }}" alt="{{ $item->product_name ?? $item->name }}">
                            @endif
                            <div class="flex-1"><p>{{ $item->product_name ?? $item->name }} × {{ $item->quantity }}</p><p class="text-gold">RM {{ number_format($item->line_total ?? $item->total, 2) }}</p></div>
                        </div>
                    @endforeach
                </section>

                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Order timeline</h2>
                    <ol class="mt-5 space-y-3 border-l border-gold/50 pl-5">
                        @foreach($timeline as $entry)
                            @if($entry[1])
                                <li><span class="text-gold">{{ $entry[0] }}</span><span class="ml-2 text-sm text-cream/60">{{ $entry[1]->format('d M Y H:i') }}</span></li>
                            @endif
                        @endforeach
                        @if($order->order_status === 'cancelled')
                            <li class="text-gold">Cancelled{{ $order->cancellation_reason ? ': '.$order->cancellation_reason : '' }}</li>
                        @endif
                    </ol>
                </section>

                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Receipt history</h2>
                    @forelse($order->paymentReceipts->sortByDesc('created_at') as $receipt)
                        <p class="mt-3 capitalize">{{ $receipt->status }} · {{ ($receipt->submitted_at ?? $receipt->created_at)->format('d M Y H:i') }}@if($receipt->status === 'rejected' && $receipt->rejection_reason) — {{ $receipt->rejection_reason }}@endif</p>
                    @empty
                        <p class="mt-3 text-cream/65">No receipt has been uploaded yet.</p>
                    @endforelse
                </section>
            </div>

            <aside class="border border-cream/15 p-6">
                <p><span class="text-cream/60">Payment Status</span><br><span class="capitalize text-gold">{{ $order->payment_status }}</span></p>
                <p class="mt-3"><span class="text-cream/60">Receipt Status</span><br><span class="capitalize text-gold">{{ $latestReceipt ? $latestReceipt->status : 'awaiting receipt' }}</span></p>
                <p class="mt-3"><span class="text-cream/60">Order Status</span><br><span class="capitalize text-gold">{{ $order->order_status }}</span></p>
                <p class="mt-5">Shipping method: {{ $order->shipping_method_name ?? '—' }}</p>
                <p>Shipping fee: RM {{ number_format($order->shipping_fee, 2) }}</p>
                <p>Courier: {{ $order->courier_name ?: 'Awaiting dispatch' }}</p>
                <p>Tracking: {{ $order->tracking_number ?: '—' }}</p>
                <p class="mt-5">Subtotal <span class="float-right">RM {{ number_format($order->subtotal, 2) }}</span></p>
                <p>Shipping <span class="float-right">RM {{ number_format($order->shipping_fee, 2) }}</span></p>
                <p class="mt-4 text-2xl">Total <span class="float-right text-gold">RM {{ number_format($order->total, 2) }}</span></p>

                @if($order->payment_status === 'paid')
                    <p class="mt-8 border border-gold/40 p-4 text-gold">Payment Approved</p>
                @elseif($latestReceipt && $latestReceipt->status === 'pending')
                    <p class="mt-8 border border-gold/40 p-4 text-gold">Receipt pending review.</p>
                @elseif($canUploadReceipt)
                    <form class="mt-8 space-y-3" method="POST" enctype="multipart/form-data" action="{{ route('orders.payment-receipts.store', ['order' => $order->order_number, 'token' => $token]) }}">
                        @csrf
                        <label>Upload {{ $latestReceipt && $latestReceipt->status === 'rejected' ? 'replacement ' : '' }}receipt<input class="field mt-2" type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label>
                        @error('receipt')<p class="text-gold">{{ $message }}</p>@enderror
                        <button class="luxury-link" type="submit">Upload receipt</button>
                    </form>
                @endif
            </aside>
        </div>
    </section>
</x-layouts.store>
