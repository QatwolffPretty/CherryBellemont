<x-layouts.admin :title="$order->order_number ?? $order->number">
    <x-admin.section width="7xl">
        <x-admin.page-header :title="$order->order_number ?? $order->number">
            <x-slot:breadcrumb>
                <x-admin.button variant="outline" :href="route('admin.orders.index')">Back to orders</x-admin.button>
            </x-slot:breadcrumb>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        @php($latestReceipt = $order->paymentReceipts->sortByDesc('created_at')->first())

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_24rem]">
            <div class="space-y-6">
                <x-admin.card title="Customer & delivery">
                    <p class="mt-4">{{ $order->customer_name }}<br>{{ $order->customer_email }} &middot; {{ $order->customer_phone }}</p>
                    @if($order->pickup_location)
                        <p class="mt-4">Pickup: {{ $order->pickup_location }}</p>
                    @else
                        <p class="mt-4">{{ $order->address_line_1 }} {{ $order->address_line_2 }}<br>{{ $order->city }}, {{ $order->state }} {{ $order->postcode }}<br>{{ $order->country }}</p>
                    @endif
                    <p class="mt-4">Delivery method: {{ $order->shipping_method_name ?? '—' }}</p>
                    <p>Delivery instructions: {{ $order->delivery_instructions ?: '—' }}</p>
                </x-admin.card>

                <x-admin.card title="Order items">
                    @foreach($order->items as $item)
                        <div class="mt-5 flex gap-4 border-b border-cream/15 pb-4">
                            @if($item->product && $item->product->image_path)
                                <img class="h-20 w-16 object-cover" src="{{ asset('storage/'.$item->product->image_path) }}" alt="{{ $item->product_name ?? $item->name }}">
                            @endif
                            <div class="flex-1"><p>{{ $item->product_name ?? $item->name }} &times; {{ $item->quantity }}</p><p class="text-gold">RM {{ number_format($item->line_total ?? $item->total, 2) }}</p></div>
                        </div>
                    @endforeach
                    <p class="mt-5">Subtotal <span class="float-right">RM {{ number_format($order->subtotal, 2) }}</span></p>
                    <p>Shipping fee <span class="float-right">RM {{ number_format($order->shipping_fee, 2) }}</span></p>
                    <p class="mt-3 text-xl text-gold">Total <span class="float-right">RM {{ number_format($order->total, 2) }}</span></p>
                </x-admin.card>
            </div>

            <x-admin.card>
                <form class="space-y-4" method="POST" action="{{ route('admin.orders.update', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p><span class="text-cream/60">Payment Status</span><br><x-admin.badge :status="$order->payment_status" /></p>
                    <p><span class="text-cream/60">Order Status</span><br><x-admin.badge :status="$order->order_status" /></p>
                    <p><span class="text-cream/60">Receipt Status</span><br><x-admin.badge :status="$latestReceipt?->status ?? 'pending'" :label="$latestReceipt?->status ?? 'Awaiting receipt'" /></p>
                    <p class="text-sm text-cream/65">Created: {{ $order->created_at->format('d M Y H:i') }}<br>Paid: {{ $latestReceipt && $latestReceipt->reviewed_at ? $latestReceipt->reviewed_at->format('d M Y H:i') : '—' }}<br>Shipped: {{ $order->shipped_at ? $order->shipped_at->format('d M Y H:i') : '—' }}<br>Delivered: {{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i') : '—' }}</p>
                    <x-admin.select name="order_status" label="Fulfilment status">
                        @foreach(['pending','payment_review','paid','processing','packed','shipped','delivered','cancelled'] as $status)
                            <option value="{{ $status }}" @selected($order->order_status === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.form-input name="courier_name" label="Courier" :value="$order->courier_name" />
                    <x-admin.form-input name="tracking_number" label="Tracking number" :value="$order->tracking_number" />
                    <x-admin.textarea name="cancellation_reason" label="Cancellation reason" :value="$order->cancellation_reason" />
                    <x-admin.textarea name="admin_notes" label="Admin notes" :value="$order->admin_notes" />
                    <x-admin.button type="submit">Save update</x-admin.button>
                </form>
            </x-admin.card>
        </div>
    </x-admin.section>
</x-layouts.admin>
