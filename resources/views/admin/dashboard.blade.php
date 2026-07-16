<x-layouts.admin title="Dashboard | Atelier">
    @php
        $chartPayload = [
            'revenue' => $dashboard['revenue_chart'],
            'statuses' => $dashboard['status_chart'],
        ];
    @endphp

    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Atelier intelligence" title="Business overview" subtitle="A live view of sales, payments, fulfilment, and customer activity.">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.reports.index')" icon="bi-graph-up">Reports</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mt-8">
            <form class="grid gap-4 md:grid-cols-4 xl:grid-cols-5" method="GET" action="{{ route('admin.dashboard') }}">
                <x-admin.select name="range" label="Dashboard period" class="mt-0">
                    @foreach($rangeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['range'] === $value)>{{ $label }}</option>
                    @endforeach
                </x-admin.select>
                <x-admin.form-input name="from_date" type="date" label="From date" :value="$filters['from_date']" class="mt-0" />
                <x-admin.form-input name="to_date" type="date" label="To date" :value="$filters['to_date']" class="mt-0" />
                <div class="flex items-end">
                    <x-admin.button type="submit" class="w-full" icon="bi-funnel">Apply period</x-admin.button>
                </div>
                <p class="self-end text-sm leading-6 text-cream/60">Charts reflect {{ strtolower($dashboard['period']['label']) }}. Custom dates are inclusive.</p>
            </form>
        </x-admin.card>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($dashboard['summary_cards'] as $card)
                <x-admin.stats-card :label="$card['label']" :value="$card['value']" :subtitle="$card['subtitle'] ?? null" :href="$card['href']" :accent="$card['accent'] ?? false" />
            @endforeach
        </div>

        <div class="mt-10 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Paid revenue — {{ $dashboard['period']['label'] }}">
                <div class="admin-chart-canvas mt-6"><canvas id="admin-revenue-chart" aria-label="Paid revenue by date" role="img"></canvas></div>
                <x-admin.table class="mt-6">
                    <x-slot:head><tr><th>Date</th><th>Paid revenue</th><th>Paid orders</th></tr></x-slot:head>
                    @forelse($dashboard['revenue_chart']['rows'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td>RM {{ number_format($row['revenue'], 2) }}</td><td>{{ $row['order_count'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-cream/60">No paid orders in this period.</td></tr>
                    @endforelse
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Order volume by fulfilment">
                <div class="admin-chart-canvas mt-6"><canvas id="admin-status-chart" aria-label="Order volume by fulfilment status" role="img"></canvas></div>
                <p class="mt-6 text-sm leading-6 text-cream/60">Payment and fulfilment remain separate: these counts represent order fulfilment status only.</p>
            </x-admin.card>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Payment method overview" class="lg:col-span-1">
                <dl class="mt-6 grid gap-5">
                    <div class="border-b border-cream/15 pb-4"><dt class="text-sm text-cream/60">Stripe paid revenue</dt><dd class="mt-1 text-2xl text-gold">RM {{ number_format($dashboard['payment_overview']['stripe_revenue'], 2) }}</dd><p class="mt-1 text-sm text-cream/60">{{ $dashboard['payment_overview']['stripe_orders'] }} paid order(s)</p></div>
                    <div class="border-b border-cream/15 pb-4"><dt class="text-sm text-cream/60">DuitNow paid revenue</dt><dd class="mt-1 text-2xl text-gold">RM {{ number_format($dashboard['payment_overview']['duitnow_revenue'], 2) }}</dd><p class="mt-1 text-sm text-cream/60">{{ $dashboard['payment_overview']['duitnow_orders'] }} paid order(s)</p></div>
                    <div><dt class="text-sm text-cream/60">Pending DuitNow reviews</dt><dd class="mt-1 text-2xl text-gold">{{ $dashboard['payment_overview']['pending_duitnow_receipts'] }}</dd><a class="mt-2 inline-block text-sm text-gold hover:text-cream" href="{{ route('admin.payment-receipts.index', ['status' => 'pending']) }}">Open verification queue</a></div>
                </dl>
            </x-admin.card>

            <x-admin.card title="Top-selling products" class="lg:col-span-2">
                <x-admin.table class="mt-6">
                    <x-slot:head><tr><th>Product</th><th>Units sold</th><th>Paid revenue</th><th>Current stock</th><th></th></tr></x-slot:head>
                    @forelse($dashboard['top_products'] as $item)
                        @php($product = $item['product'])
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if($product?->image_path)
                                        <img class="h-12 w-12 border border-cream/20 object-cover" src="{{ asset('storage/'.$product->image_path) }}" alt="">
                                    @else
                                        <div class="flex h-12 w-12 items-center justify-center border border-cream/20 text-gold"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
                                    @endif
                                    <span>{{ $product?->name ?: $item['name'] }}</span>
                                </div>
                            </td>
                            <td>{{ $item['units_sold'] }}</td>
                            <td>RM {{ number_format($item['paid_revenue'], 2) }}</td>
                            <td>
                                @if($product)
                                    <x-admin.badge :status="$product->stock === 0 ? 'out_of_stock' : ($product->stock <= $dashboard['low_stock_threshold'] ? 'low_stock' : 'in_stock')" :label="$product->stock" />
                                @else
                                    <span class="text-cream/60">Unavailable</span>
                                @endif
                            </td>
                            <td>@if($product)<x-admin.button variant="outline" :href="route('admin.products.edit', $product)">View Product</x-admin.button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-admin.empty-state title="No paid product sales yet." description="Top-selling pieces will appear after paid orders are placed." icon="bi-box-seam" /></td></tr>
                    @endforelse
                </x-admin.table>
            </x-admin.card>
        </div>

        <x-admin.card title="Recent orders" class="mt-10">
            <x-admin.table class="mt-6">
                <x-slot:head><tr><th>Order</th><th>Customer</th><th>Total</th><th>Provider</th><th>Payment</th><th>Fulfilment</th><th>Created</th><th></th></tr></x-slot:head>
                @forelse($dashboard['recent_orders'] as $order)
                    <tr>
                        <td>{{ $order->order_number ?: $order->number }}</td>
                        <td>{{ $order->customer_name ?: 'Customer' }}<br><small>{{ $order->customer_email ?: 'No email recorded' }}</small></td>
                        <td>RM {{ number_format($order->total, 2) }}</td>
                        <td><x-admin.badge :status="$order->payment_provider ?: $order->payment_method" :label="$order->payment_provider ?: $order->payment_method ?: 'Unknown'" /></td>
                        <td><span class="mb-1 block text-xs text-cream/60">Payment</span><x-admin.badge :status="$order->payment_status" /></td>
                        <td><span class="mb-1 block text-xs text-cream/60">Fulfilment</span><x-admin.badge :status="$order->order_status" /></td>
                        <td>{{ $order->created_at?->format('d M Y, H:i') }}</td>
                        <td><x-admin.button variant="outline" :href="route('admin.orders.show', $order)">View</x-admin.button></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty-state title="No orders yet." description="New orders will appear here as customers place them." icon="bi-handbag-fill" /></td></tr>
                @endforelse
            </x-admin.table>
        </x-admin.card>

        <div class="mt-10 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Low-stock visibility">
                <p class="mt-3 text-sm text-cream/60">Showing products at or below the configured threshold of {{ $dashboard['low_stock_threshold'] }}.</p>
                <x-admin.table class="mt-6">
                    <x-slot:head><tr><th>Product</th><th>Current stock</th><th>Status</th><th></th></tr></x-slot:head>
                    @forelse($dashboard['low_stock_products'] as $product)
                        <tr>
                            <td>{{ $product->name }}<br><small class="text-cream/60">SKU unavailable</small></td>
                            <td>{{ $product->stock }}</td>
                            <td><x-admin.badge :status="$product->stock === 0 ? 'out_of_stock' : 'low_stock'" :label="$product->stock === 0 ? 'Out of Stock' : 'Low Stock'" /></td>
                            <td><x-admin.button variant="outline" :href="route('admin.products.edit', $product)">Edit Product</x-admin.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-admin.empty-state title="Stock levels are healthy." description="No products are at or below the configured threshold." icon="bi-check2-circle" /></td></tr>
                    @endforelse
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Recent activity">
                <ol class="mt-6 divide-y divide-cream/15">
                    @forelse($dashboard['activity'] as $item)
                        <li class="flex gap-4 py-4 first:pt-0">
                            <i class="bi {{ $item['icon'] }} mt-1 text-gold" aria-hidden="true"></i>
                            <div class="min-w-0 flex-1"><p>{{ $item['title'] }}</p><p class="mt-1 truncate text-sm text-cream/60">{{ $item['detail'] }}</p></div>
                            <time class="shrink-0 text-xs text-cream/50" datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->diffForHumans() }}</time>
                        </li>
                    @empty
                        <li><x-admin.empty-state title="No recent activity." description="Orders, payments, and customer updates will appear here." icon="bi-clock-history" /></li>
                    @endforelse
                </ol>
            </x-admin.card>
        </div>
    </x-admin.section>

    <script id="admin-dashboard-chart-data" type="application/json">{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</x-layouts.admin>
