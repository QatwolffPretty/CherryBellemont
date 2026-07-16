<x-layouts.admin :title="($customer->customer_name ?: 'Customer').' | Cherry Bellemont'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Customer profile" :title="$customer->customer_name ?: 'Customer'" :subtitle="$customer->customer_email">
            <x-slot:breadcrumb><x-admin.button variant="outline" :href="route('admin.customers.index')">Back to customers</x-admin.button></x-slot:breadcrumb>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Customer summary">
                <dl class="mt-6 grid gap-4">
                    <div><dt class="text-sm text-cream/60">Account type</dt><dd class="mt-1"><x-admin.badge :status="$customer->registered ? 'active' : 'pending'" :label="$customer->registered ? 'Registered' : 'Guest'" /></dd></div>
                    <div><dt class="text-sm text-cream/60">Phone</dt><dd>{{ $customer->customer_phone ?: 'Not recorded' }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Newsletter</dt><dd><x-admin.badge :status="$customer->newsletter_status === 'subscribed' ? 'active' : 'pending'" :label="$customer->newsletter_status" /></dd></div>
                    <div><dt class="text-sm text-cream/60">First order</dt><dd>{{ $customer->first_order_at ? \Illuminate\Support\Carbon::parse($customer->first_order_at)->format('d M Y, H:i') : '—' }}</dd></div>
                    <div><dt class="text-sm text-cream/60">Latest order</dt><dd>{{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d M Y, H:i') : '—' }}</dd></div>
                </dl>
            </x-admin.card>

            <x-admin.card title="Order value" class="lg:col-span-2">
                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div><p class="text-sm text-cream/60">Total orders</p><p class="mt-1 text-3xl text-gold">{{ $customer->total_orders }}</p></div>
                    <div><p class="text-sm text-cream/60">Paid orders</p><p class="mt-1 text-3xl text-gold">{{ $customer->paid_orders }}</p></div>
                    <div><p class="text-sm text-cream/60">Total paid spend</p><p class="mt-1 text-3xl text-gold">RM {{ number_format($customer->total_spent, 2) }}</p></div>
                    <div><p class="text-sm text-cream/60">Average order</p><p class="mt-1 text-3xl text-gold">RM {{ number_format($customer->average_order_value, 2) }}</p></div>
                </div>
                <div class="mt-8 grid gap-6 md:grid-cols-2">
                    <div><p class="text-sm text-cream/60">Payment methods used</p><p class="mt-2 capitalize">{{ $paymentMethods->map(fn ($method) => str($method)->replace('_', ' ')->title())->join(', ') ?: '—' }}</p></div>
                    <div><p class="text-sm text-cream/60">Coupons used</p><p class="mt-2">{{ $coupons->join(', ') ?: 'No coupons used' }}</p></div>
                </div>
            </x-admin.card>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Known delivery addresses">
                <div class="mt-6 space-y-4">
                    @forelse($addresses as $address)<p class="whitespace-pre-line border-b border-cream/15 pb-4 last:border-0">{{ $address }}</p>@empty<p class="text-cream/60">No delivery addresses are recorded.</p>@endforelse
                </div>
            </x-admin.card>

            <x-admin.card title="Internal notes">
                <form class="mt-6" method="POST" action="{{ route('admin.customers.notes.store', ['email' => $customer->customer_email]) }}">
                    @csrf
                    <x-admin.textarea name="note" label="Add internal note" required help="Visible only to administrators. Do not add payment-sensitive information." />
                    <x-admin.button class="mt-4" type="submit" icon="bi-plus-lg">Add note</x-admin.button>
                </form>
                <div class="mt-6 space-y-4">
                    @forelse($notes as $note)
                        <article class="border-t border-cream/15 pt-4"><p class="whitespace-pre-line">{{ $note->note }}</p><p class="mt-2 text-xs text-cream/55">{{ $note->admin?->name ?: 'Former administrator' }} · {{ $note->created_at?->format('d M Y, H:i') }}</p></article>
                    @empty
                        <p class="text-cream/60">No internal notes have been added.</p>
                    @endforelse
                </div>
            </x-admin.card>
        </div>

        <x-admin.card title="Order history" class="mt-8">
            <x-admin.table class="mt-6">
                <x-slot:head><tr><th>Order</th><th>Date</th><th>Total</th><th>Provider</th><th>Payment</th><th>Fulfilment</th><th>Delivery</th><th></th></tr></x-slot:head>
                @forelse($orders as $order)
                    <tr>
                        <td>{{ $order->order_number ?: $order->number }}</td>
                        <td>{{ $order->created_at?->format('d M Y, H:i') }}</td>
                        <td>RM {{ number_format($order->total, 2) }}</td>
                        <td class="capitalize">{{ $order->payment_provider ?: $order->payment_method ?: '—' }}</td>
                        <td><x-admin.badge :status="$order->payment_status" /></td>
                        <td><x-admin.badge :status="$order->order_status" /></td>
                        <td>{{ $order->shipping_method_name ?: $order->deliveryMethod?->name ?: '—' }}</td>
                        <td><x-admin.button variant="outline" :href="route('admin.orders.show', $order)">View order</x-admin.button></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty-state title="No orders found." description="This customer has no matching orders." icon="bi-handbag-fill" /></td></tr>
                @endforelse
            </x-admin.table>
            @if($orders->hasPages())<div class="mt-6">{{ $orders->links() }}</div>@endif
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
