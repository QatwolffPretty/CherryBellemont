<x-layouts.admin title="Reports | Cherry Bellemont">
    @php
        $chartPayload = $report['charts'];
        $exportUrl = fn (string $name) => route('admin.reports.export', array_merge(['report' => $name], $filters));
    @endphp

    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Atelier intelligence" title="Reports" subtitle="Paid, non-cancelled revenue is shown separately from payment and fulfilment activity." />

        <x-admin.card class="mt-8">
            <form class="grid gap-4 md:grid-cols-4 xl:grid-cols-5" method="GET" action="{{ route('admin.reports.index') }}">
                <x-admin.select class="mt-0" name="range" label="Report period">
                    @foreach($rangeOptions as $value => $label)<option value="{{ $value }}" @selected($filters['range'] === $value)>{{ $label }}</option>@endforeach
                </x-admin.select>
                <x-admin.form-input class="mt-0" name="from_date" type="date" label="From date" :value="$filters['from_date']" />
                <x-admin.form-input class="mt-0" name="to_date" type="date" label="To date" :value="$filters['to_date']" />
                <div class="flex items-end"><x-admin.button class="w-full" type="submit" icon="bi-funnel">Apply period</x-admin.button></div>
                <p class="self-end text-sm leading-6 text-cream/60">Reporting {{ strtolower($report['period']['label']) }}. Custom dates are inclusive.</p>
            </form>
        </x-admin.card>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Sales">
                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-admin.stats-card label="Gross paid revenue" :value="'RM '.number_format($report['sales']['gross_paid_revenue'], 2)" />
                    <x-admin.stats-card label="Discounts issued" :value="'RM '.number_format($report['sales']['discounts'], 2)" />
                    <x-admin.stats-card label="Shipping revenue" :value="'RM '.number_format($report['sales']['shipping_revenue'], 2)" />
                    <x-admin.stats-card label="Net order revenue" :value="'RM '.number_format($report['sales']['net_order_revenue'], 2)" />
                    <x-admin.stats-card label="Successful refunds" :value="'RM '.number_format($report['sales']['successful_refunds'], 2)" />
                    <x-admin.stats-card label="Paid orders" :value="$report['sales']['paid_orders']" />
                    <x-admin.stats-card label="Average order value" :value="'RM '.number_format($report['sales']['average_order_value'], 2)" />
                    <x-admin.stats-card label="Gift orders" :value="$report['sales']['gift_orders']" />
                    <x-admin.stats-card label="Gift wrapping revenue" :value="'RM '.number_format($report['sales']['gift_wrapping_revenue'], 2)" />
                    <x-admin.stats-card label="Gift wrapping usage" :value="number_format($report['sales']['gift_wrapping_usage_rate'], 1).'%'" />
                </div>
                <div class="admin-chart-canvas mt-8"><canvas id="admin-reports-revenue-chart" aria-label="Paid revenue trend" role="img"></canvas></div>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('sales')" icon="bi-download">Export sales CSV</x-admin.button></div>
                <p class="mt-4 text-sm text-cream/60">Refunded orders are excluded from paid revenue. {{ $report['sales']['refunded_orders'] }} refunded order(s) appear separately in this period.</p>
            </x-admin.card>

            <x-admin.card title="Orders & fulfilment">
                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-admin.stats-card label="Total orders" :value="$report['orders']['total_orders']" />
                    <x-admin.stats-card label="Paid orders" :value="$report['orders']['paid_orders']" />
                    <x-admin.stats-card label="Pending payment" :value="$report['orders']['pending_payment_orders']" />
                    @foreach(['processing' => 'Processing', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $status => $label)<x-admin.stats-card :label="$label" :value="$report['orders']['statuses'][$status]" />@endforeach
                </div>
                <div class="admin-chart-canvas mt-8"><canvas id="admin-reports-status-chart" aria-label="Order fulfilment status chart" role="img"></canvas></div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 text-sm text-cream/60"><p>Fulfilment rate: <span class="text-gold">{{ number_format($report['orders']['fulfilment_rate'], 1) }}%</span></p><p>Cancellation rate: <span class="text-gold">{{ number_format($report['orders']['cancellation_rate'], 1) }}%</span></p></div>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('orders')" icon="bi-download">Export orders CSV</x-admin.button></div>
            </x-admin.card>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Payments">
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-admin.stats-card label="Stripe paid revenue" :value="'RM '.number_format($report['payments']['stripe_revenue'], 2)" />
                    <x-admin.stats-card label="DuitNow paid revenue" :value="'RM '.number_format($report['payments']['duitnow_revenue'], 2)" />
                    <x-admin.stats-card label="Stripe paid orders" :value="$report['payments']['stripe_orders']" />
                    <x-admin.stats-card label="DuitNow paid orders" :value="$report['payments']['duitnow_orders']" />
                    <x-admin.stats-card label="Pending DuitNow receipts" :value="$report['payments']['pending_duitnow_receipts']" />
                    <x-admin.stats-card label="Rejected receipts" :value="$report['payments']['rejected_receipts']" />
                </div>
                <div class="admin-chart-canvas mt-8"><canvas id="admin-reports-payment-chart" aria-label="Payment provider revenue chart" role="img"></canvas></div>
                <p class="mt-5 text-sm text-cream/60">Payment failures recorded in this period: {{ $report['payments']['payment_failures'] }}. Stripe payments are never included in the DuitNow receipt queue.</p>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('payments')" icon="bi-download">Export payments CSV</x-admin.button></div>
            </x-admin.card>

            <x-admin.card title="Customers">
                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-admin.stats-card label="Unique customers" :value="$report['customers']['unique_customers']" />
                    <x-admin.stats-card label="New customers" :value="$report['customers']['new_customers']" />
                    <x-admin.stats-card label="Returning customers" :value="$report['customers']['returning_customers']" />
                    <x-admin.stats-card label="Registered" :value="$report['customers']['registered_customers']" />
                    <x-admin.stats-card label="Guest" :value="$report['customers']['guest_customers']" />
                    <x-admin.stats-card label="Average paid spend" :value="'RM '.number_format($report['customers']['average_customer_spend'], 2)" />
                </div>
                <div class="admin-chart-canvas mt-8"><canvas id="admin-reports-customer-chart" aria-label="New customer chart" role="img"></canvas></div>
                <p class="mt-5 text-sm text-cream/60">Repeat-purchase rate: <span class="text-gold">{{ number_format($report['customers']['repeat_purchase_rate'], 1) }}%</span>. Customers are identified by normalized email.</p>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('customers')" icon="bi-download">Export customers CSV</x-admin.button></div>
            </x-admin.card>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Products">
                <div class="admin-chart-canvas mt-6"><canvas id="admin-reports-product-chart" aria-label="Top product units sold chart" role="img"></canvas></div>
                <p class="mt-6 text-sm text-cream/60">Top-selling products by units</p>
                <x-admin.table class="mt-3"><x-slot:head><tr><th>Top product</th><th>Units sold</th><th>Paid revenue</th><th>Stock</th></tr></x-slot:head>
                    @forelse($report['products']['top_by_units'] as $product)<tr><td>{{ $product->product_name }}</td><td>{{ $product->units_sold }}</td><td>RM {{ number_format($product->paid_revenue, 2) }}</td><td>{{ $product->stock ?? 'Unavailable' }}</td></tr>@empty<tr><td colspan="4" class="text-cream/60">No paid product sales in this period.</td></tr>@endforelse
                </x-admin.table>
                <p class="mt-6 text-sm text-cream/60">Top products by paid revenue</p>
                <x-admin.table class="mt-3"><x-slot:head><tr><th>Product</th><th>Paid revenue</th><th>Units sold</th></tr></x-slot:head>
                    @forelse($report['products']['top_by_revenue'] as $product)<tr><td>{{ $product->product_name }}</td><td>RM {{ number_format($product->paid_revenue, 2) }}</td><td>{{ $product->units_sold }}</td></tr>@empty<tr><td colspan="3" class="text-cream/60">No paid product revenue in this period.</td></tr>@endforelse
                </x-admin.table>
                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="text-sm text-cream/60">Lowest-selling products</p>
                        <x-admin.table class="mt-3">
                            <x-slot:head><tr><th>Product</th><th>Units</th></tr></x-slot:head>
                            @forelse($report['products']['lowest_selling'] as $product)
                                <tr><td>{{ $product->product_name }}</td><td>{{ $product->units_sold }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-cream/60">No sales data.</td></tr>
                            @endforelse
                        </x-admin.table>
                    </div>
                    <div>
                        <p class="text-sm text-cream/60">Products with no sales in this period</p>
                        <x-admin.table class="mt-3">
                            <x-slot:head><tr><th>Product</th><th>Stock</th></tr></x-slot:head>
                            @forelse($report['products']['no_sales'] as $product)
                                <tr><td>{{ $product->name }}</td><td>{{ $product->stock }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-cream/60">Every active product has a paid sale.</td></tr>
                            @endforelse
                        </x-admin.table>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('products')" icon="bi-download">Export products CSV</x-admin.button></div>
            </x-admin.card>

            <x-admin.card title="Inventory">
                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-admin.stats-card label="Active products" :value="$report['inventory']['active_products']" />
                    <x-admin.stats-card label="Units in stock" :value="$report['inventory']['units_in_stock']" />
                    <x-admin.stats-card label="Low stock" :value="$report['inventory']['low_stock_products']" />
                    <x-admin.stats-card label="Out of stock" :value="$report['inventory']['out_of_stock_products']" />
                    <x-admin.stats-card label="Units sold" :value="$report['inventory']['units_sold']" />
                    <x-admin.stats-card label="Units restored" :value="$report['inventory']['units_restored']" />
                </div>
                <p class="mt-6 text-sm text-cream/60">Low stock uses the configured threshold of {{ $report['inventory']['threshold'] }}. Inventory value is omitted because product cost prices are not stored.</p>
                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="text-sm text-cream/60">Low-stock products</p>
                        <x-admin.table class="mt-3">
                            <x-slot:head><tr><th>Product</th><th>Stock</th></tr></x-slot:head>
                            @forelse($report['products']['low_stock'] as $product)
                                <tr><td>{{ $product->name }}</td><td>{{ $product->stock }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-cream/60">No low-stock products.</td></tr>
                            @endforelse
                        </x-admin.table>
                    </div>
                    <div>
                        <p class="text-sm text-cream/60">Out-of-stock products</p>
                        <x-admin.table class="mt-3">
                            <x-slot:head><tr><th>Product</th><th>Stock</th></tr></x-slot:head>
                            @forelse($report['products']['out_of_stock'] as $product)
                                <tr><td>{{ $product->name }}</td><td>{{ $product->stock }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-cream/60">No out-of-stock products.</td></tr>
                            @endforelse
                        </x-admin.table>
                    </div>
                </div>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('inventory')" icon="bi-download">Export inventory CSV</x-admin.button></div>
            </x-admin.card>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <x-admin.card title="Coupons">
                <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-admin.stats-card label="Active coupons" :value="$report['coupons']['active_coupons']" />
                    <x-admin.stats-card label="Coupon uses" :value="$report['coupons']['total_uses']" />
                    <x-admin.stats-card label="Discount value issued" :value="'RM '.number_format($report['coupons']['discount_issued'], 2)" />
                    <x-admin.stats-card label="Coupon order revenue" :value="'RM '.number_format($report['coupons']['coupon_order_revenue'], 2)" />
                    <x-admin.stats-card label="Expired coupons" :value="$report['coupons']['expired_coupons']" />
                    <x-admin.stats-card label="Approaching limits" :value="$report['coupons']['approaching_limits']->count()" />
                </div>
                <p class="mt-6 text-sm text-cream/60">Most used: <span class="text-gold">{{ $report['coupons']['most_used']?->code ?: '—' }}</span></p>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('coupons')" icon="bi-download">Export coupons CSV</x-admin.button></div>
            </x-admin.card>

            <x-admin.card title="Newsletter">
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-admin.stats-card label="Active subscribers" :value="$report['newsletter']['active_subscribers']" />
                    <x-admin.stats-card label="New subscribers" :value="$report['newsletter']['new_subscribers']" />
                    <x-admin.stats-card label="Unsubscribed" :value="$report['newsletter']['unsubscribed']" />
                    <x-admin.stats-card label="Total subscribers" :value="$report['newsletter']['total_subscribers']" />
                </div>
                <div class="admin-chart-canvas mt-8"><canvas id="admin-reports-newsletter-chart" aria-label="Newsletter subscriber growth chart" role="img"></canvas></div>
                <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('newsletter')" icon="bi-download">Export newsletter CSV</x-admin.button></div>
            </x-admin.card>
        </div>

        <x-admin.card title="Returns & refunds" class="mt-8">
            <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.stats-card label="Requests" :value="$report['returns']['requests']" />
                <x-admin.stats-card label="Pending review" :value="$report['returns']['pending_review']" />
                <x-admin.stats-card label="Awaiting return" :value="$report['returns']['awaiting_return']" />
                <x-admin.stats-card label="Completed / closed" :value="$report['returns']['completed']" />
                <x-admin.stats-card label="Refunds processing" :value="$report['returns']['refund_processing']" />
                <x-admin.stats-card label="Failed refunds" :value="$report['returns']['refund_failed']" />
                <x-admin.stats-card label="Confirmed refunds" :value="'RM '.number_format($report['returns']['refund_succeeded'], 2)" />
            </div>
            <div class="mt-6 flex justify-end"><x-admin.button variant="outline" :href="$exportUrl('returns')" icon="bi-download">Export returns CSV</x-admin.button></div>
            <p class="mt-4 text-sm text-cream/60">Net order revenue subtracts successful refunds confirmed in the selected period. Shipping and gift wrapping refunds are excluded unless an administrator explicitly recorded them.</p>
        </x-admin.card>

        <x-admin.card title="Top customers by paid spend" class="mt-8">
            <x-admin.table class="mt-6"><x-slot:head><tr><th>Customer</th><th>Paid orders</th><th>Paid spend</th></tr></x-slot:head>
                @forelse($report['customers']['top_customers'] as $customer)<tr><td>{{ $customer->customer_name ?: 'Customer' }}<br><small>{{ $customer->customer_email }}</small></td><td>{{ $customer->paid_orders }}</td><td>RM {{ number_format($customer->total_spent, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-cream/60">No paid customer spend in this period.</td></tr>@endforelse
            </x-admin.table>
        </x-admin.card>
    </x-admin.section>

    <script id="admin-reports-chart-data" type="application/json">{!! json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</x-layouts.admin>
