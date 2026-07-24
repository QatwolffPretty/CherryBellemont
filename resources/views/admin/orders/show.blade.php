<x-layouts.admin :title="$order->order_number ?? $order->number">
    <x-admin.section width="7xl">
        <x-admin.page-header :title="$order->order_number ?? $order->number">
            <x-slot:breadcrumb>
                <x-admin.button variant="outline" :href="route('admin.orders.index')">Back to orders</x-admin.button>
            </x-slot:breadcrumb>
            <x-slot:actions>
                @if($order->payment_status === 'paid')
                    <x-admin.button variant="outline" icon="bi-file-earmark-pdf" :href="route('admin.orders.invoice', $order)">Download Invoice</x-admin.button>
                @endif
                <x-admin.button variant="outline" icon="bi-printer" :href="route('admin.orders.packing-slip', $order)" target="_blank">Print Packing Slip</x-admin.button>
                @if($order->latestShipment?->label_path)
                    <x-admin.button variant="outline" icon="bi-file-earmark-arrow-down" :href="route('admin.shipments.label.download', $order->latestShipment)">Download Shipment Label</x-admin.button>
                @endif
                @if($order->payment_status === 'paid' && $order->order_status === 'packed' && ! $order->latestShipment?->shipment_status)
                    <x-admin.button icon="bi-truck" :href="route('admin.orders.shipments.create', $order)">Create Shipment</x-admin.button>
                @elseif($order->latestShipment)
                    <x-admin.button variant="outline" icon="bi-truck" :href="route('admin.shipments.show', $order->latestShipment)">View Shipment</x-admin.button>
                @endif
            </x-slot:actions>
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
                    <p>Coupon <span class="float-right">{{ $order->coupon_code ?: '—' }}</span></p>
                    <p>Discount <span class="float-right text-gold">−RM {{ number_format($order->discount_amount ?? 0, 2) }}</span></p>
                    <p>Original shipping <span class="float-right">RM {{ number_format($order->original_shipping_fee ?? $order->shipping_fee, 2) }}</span></p>
                    <p>Shipping fee <span class="float-right">RM {{ number_format($order->shipping_fee, 2) }}</span></p>
                    <p>Free-shipping discount <span class="float-right text-gold">−RM {{ number_format($order->free_shipping_discount ?? 0, 2) }}</span></p>
                    <p>Signature Gift Experience <span class="float-right {{ $order->gift_wrapping ? 'text-gold' : '' }}">{{ $order->gift_wrapping ? 'Yes' : 'No' }}</span></p>
                    @if($order->gift_wrapping)
                        <p>Gift wrapping fee <span class="float-right text-gold">RM {{ number_format($order->gift_wrapping_fee, 2) }}</span></p>
                        <p class="mt-3 border-t border-gold/35 pt-3 text-gold">Gift message</p>
                        <p class="mt-1 whitespace-pre-line text-cream/75">{{ $order->gift_message ?: 'No personalised message.' }}</p>
                    @endif
                    <p class="mt-3 text-xl text-gold">Total <span class="float-right">RM {{ number_format($order->total, 2) }}</span></p>
                </x-admin.card>

                <x-admin.card title="Returns & refunds">
                    <p class="mt-4">Return status: <x-admin.badge :status="$order->return_status" :label="$order->return_status ? null : 'No request'" /></p>
                    <p class="mt-3">Refunded amount <span class="float-right text-gold">RM {{ number_format((float) ($order->refunded_amount ?? 0), 2) }}</span></p>
                    <p>Refund status <span class="float-right capitalize">{{ $order->refund_status ?: '—' }}</span></p>
                    @forelse($order->returnRequests as $returnRequest)
                        <div class="mt-4 flex items-center justify-between border-t border-cream/15 pt-4"><div><p>{{ $returnRequest->return_number }}</p><p class="mt-1 text-sm capitalize text-cream/60">{{ str($returnRequest->status)->replace('_', ' ') }}</p></div><x-admin.button variant="outline" :href="route('admin.returns.show', $returnRequest)">View return</x-admin.button></div>
                    @empty
                        <p class="mt-4 text-sm text-cream/65">No return request has been submitted.</p>
                    @endforelse
                </x-admin.card>

                <x-admin.card title="Shipment">
                    @if($order->latestShipment)
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <p><span class="text-cream/60">Shipment</span><br>{{ $order->latestShipment->shipment_number }}</p>
                            <p><span class="text-cream/60">Status</span><br><x-admin.badge :status="$order->latestShipment->shipment_status" /></p>
                            <p><span class="text-cream/60">Courier</span><br>{{ $order->latestShipment->courier_name_snapshot ?: '—' }}</p>
                            <p><span class="text-cream/60">Tracking</span><br>{{ $order->latestShipment->tracking_number ?: '—' }}</p>
                        </div>
                        <x-admin.button class="mt-5" variant="outline" :href="route('admin.shipments.show', $order->latestShipment)">Manage Shipment</x-admin.button>
                    @else
                        <p class="mt-4 text-cream/65">No shipment has been created.</p>
                        @if($order->payment_status === 'paid' && $order->order_status === 'packed')
                            <x-admin.button class="mt-5" :href="route('admin.orders.shipments.create', $order)" icon="bi-truck">Create Shipment</x-admin.button>
                        @endif
                    @endif
                </x-admin.card>
            </div>

            <x-admin.card>
                <form class="space-y-4" method="POST" action="{{ route('admin.orders.update', $order) }}">
                    @csrf
                    @method('PATCH')
                    <p><span class="text-cream/60">Payment Provider</span><br><x-admin.badge :status="$order->payment_provider ?? $order->payment_method" :label="$order->payment_provider ?? $order->payment_method" /></p>
                    <p><span class="text-cream/60">Payment Status</span><br><x-admin.badge :status="$order->payment_status" /></p>
                    <p><span class="text-cream/60">Order Status</span><br><x-admin.badge :status="$order->order_status" /></p>
                    <p><span class="text-cream/60">Receipt Status</span><br><x-admin.badge :status="$order->payment_method === 'stripe' ? 'approved' : ($latestReceipt?->status ?? 'pending')" :label="$order->payment_method === 'stripe' ? 'Not required' : ($latestReceipt?->status ?? 'Awaiting receipt')" /></p>
                    @if(($order->payment_provider ?? $order->payment_method) === 'stripe')
                        <div class="border-t border-cream/15 pt-4 text-sm text-cream/65">
                            <p>Stripe session: {{ $order->stripe_checkout_session_id ? \Illuminate\Support\Str::limit($order->stripe_checkout_session_id, 22, '…') : '—' }}</p>
                            <p class="mt-2">Payment intent: {{ $order->stripe_payment_intent_id ? \Illuminate\Support\Str::limit($order->stripe_payment_intent_id, 22, '…') : '—' }}</p>
                            <p class="mt-2">Stripe status: {{ $order->stripe_payment_status ?: 'awaiting payment' }}</p>
                            <p class="mt-2">Stripe paid: {{ $order->stripe_paid_at?->format('d M Y H:i') ?: '—' }}</p>
                            @if($order->stripe_failure_reason)<p class="mt-2 text-gold">{{ $order->stripe_failure_reason }}</p>@endif
                        </div>
                    @endif
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
