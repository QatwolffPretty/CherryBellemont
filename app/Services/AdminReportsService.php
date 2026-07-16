<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminReportsService
{
    /** @return array<string, string> */
    public function rangeOptions(): array
    {
        return [
            'today' => 'Today',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
            'this_year' => 'This Year',
            'custom' => 'Custom Range',
        ];
    }

    /** @param array{range?: string, from_date?: ?string, to_date?: ?string} $filters */
    public function report(array $filters): array
    {
        $period = $this->period($filters);
        $summaryKey = 'admin-reports:summary:'.sha1($period['key'].'|'.$period['start']->toDateString().'|'.$period['end']->toDateString());
        $summary = Cache::remember($summaryKey, now()->addMinutes(2), function () use ($period): array {
            return [
                'sales' => $this->sales($period),
                'orders' => $this->orders($period),
                'payments' => $this->payments($period),
                'coupons' => $this->couponSummary($period),
                'inventory' => $this->inventorySummary($period),
                'newsletter' => $this->newsletterSummary($period),
            ];
        });

        return [
            ...$summary,
            'period' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
            ],
            'products' => $this->products($period),
            'customers' => $this->customers($period),
            'charts' => [
                'revenue' => $this->revenueTrend($period),
                'statuses' => $this->statusChart($period),
                'payments' => $this->paymentChart($period),
                'products' => $this->productChart($period),
                'customers' => $this->customerGrowth($period),
                'newsletter' => $this->newsletterGrowth($period),
            ],
        ];
    }

    /** @param array{range?: string, from_date?: ?string, to_date?: ?string} $filters
     * @return array{filename: string, headings: array<int, string>, rows: iterable<int, array<int, string|int|float|null>>}
     */
    public function export(string $report, array $filters): array
    {
        $period = $this->period($filters);

        return match ($report) {
            'sales' => [
                'filename' => 'cherry-bellemont-sales-report.csv',
                'headings' => ['Date', 'Gross Paid Revenue', 'Discounts', 'Shipping Revenue', 'Net Order Revenue', 'Paid Orders'],
                'rows' => $this->revenueTrend($period)['rows']->map(fn (array $row) => [$row['date'], $row['gross'], $row['discount'], $row['shipping'], $row['revenue'], $row['order_count']]),
            ],
            'orders' => [
                'filename' => 'cherry-bellemont-orders-report.csv',
                'headings' => ['Order Number', 'Date', 'Customer', 'Payment Provider', 'Payment Status', 'Fulfilment Status', 'Total'],
                'rows' => $this->within(Order::query()->latest(), $period)->cursor()->map(fn (Order $order) => [$order->order_number ?: $order->number, optional($order->created_at)->toDateTimeString(), $order->customer_name ?: $order->customer_email, $order->payment_provider ?: $order->payment_method, $order->payment_status, $order->order_status, $order->total]),
            ],
            'products' => [
                'filename' => 'cherry-bellemont-products-report.csv',
                'headings' => ['Product', 'Units Sold', 'Paid Revenue', 'Current Stock'],
                'rows' => $this->productSales($period)->map(fn (object $row) => [$row->product_name, (int) $row->units_sold, (float) $row->paid_revenue, $row->stock]),
            ],
            'payments' => [
                'filename' => 'cherry-bellemont-payments-report.csv',
                'headings' => ['Payment Provider', 'Paid Revenue', 'Paid Orders'],
                'rows' => collect(['stripe', 'duitnow'])->map(function (string $provider) use ($period): array {
                    $orders = $this->providerOrders($provider, $period);

                    return [strtoupper($provider), (float) $orders->sum('total'), $orders->count()];
                }),
            ],
            'customers' => [
                'filename' => 'cherry-bellemont-customer-report.csv',
                'headings' => ['Customer Email', 'Customer Name', 'Paid Orders', 'Paid Spend'],
                'rows' => $this->topCustomers($period)->map(fn (object $customer) => [$customer->customer_email, $customer->customer_name, (int) $customer->paid_orders, (float) $customer->total_spent]),
            ],
            'coupons' => [
                'filename' => 'cherry-bellemont-coupon-report.csv',
                'headings' => ['Coupon', 'Uses in Period', 'Discount Value Issued'],
                'rows' => $this->couponUsageRows($period)->map(fn (object $coupon) => [$coupon->code ?: 'Deleted coupon', (int) $coupon->uses, (float) $coupon->discount_issued]),
            ],
            'inventory' => [
                'filename' => 'cherry-bellemont-inventory-report.csv',
                'headings' => ['Product', 'Status', 'Current Stock'],
                'rows' => Product::query()->orderBy('name')->cursor()->map(fn (Product $product) => [$product->name, $product->status, $product->stock]),
            ],
            'newsletter' => [
                'filename' => 'cherry-bellemont-newsletter-report.csv',
                'headings' => ['Email', 'Name', 'Status', 'Source', 'Subscribed At', 'Unsubscribed At'],
                'rows' => NewsletterSubscriber::query()
                    ->where(function (Builder $query) use ($period): void {
                        $query->whereBetween('subscribed_at', [$period['start'], $period['end']])
                            ->orWhereBetween('unsubscribed_at', [$period['start'], $period['end']]);
                    })
                    ->orderByDesc('subscribed_at')
                    ->cursor()
                    ->map(fn (NewsletterSubscriber $subscriber) => [$subscriber->email, $subscriber->name, $subscriber->status, $subscriber->source, optional($subscriber->subscribed_at)->toDateTimeString(), optional($subscriber->unsubscribed_at)->toDateTimeString()]),
            ],
            default => throw new \InvalidArgumentException('Unknown report export.'),
        };
    }

    /** @param array{range?: string, from_date?: ?string, to_date?: ?string} $filters
     * @return array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function period(array $filters): array
    {
        $today = CarbonImmutable::now();

        return match ($filters['range'] ?? 'last_30_days') {
            'today' => $this->periodResult('today', 'Today', $today->startOfDay(), $today->endOfDay()),
            'last_7_days' => $this->periodResult('last_7_days', 'Last 7 Days', $today->subDays(6)->startOfDay(), $today->endOfDay()),
            'this_month' => $this->periodResult('this_month', 'This Month', $today->startOfMonth(), $today->endOfDay()),
            'this_year' => $this->periodResult('this_year', 'This Year', $today->startOfYear(), $today->endOfDay()),
            'custom' => $this->periodResult('custom', 'Custom Range', CarbonImmutable::parse((string) $filters['from_date'])->startOfDay(), CarbonImmutable::parse((string) $filters['to_date'])->endOfDay()),
            default => $this->periodResult('last_30_days', 'Last 30 Days', $today->subDays(29)->startOfDay(), $today->endOfDay()),
        };
    }

    /** @return array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable} */
    private function periodResult(string $key, string $label, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return compact('key', 'label', 'start', 'end');
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function sales(array $period): array
    {
        $paid = $this->within($this->paidNonCancelledOrders(), $period);
        $paidCount = (clone $paid)->count();
        $net = (float) (clone $paid)->sum('total');

        return [
            'gross_paid_revenue' => (float) (clone $paid)->sum('subtotal'),
            'discounts' => (float) (clone $paid)->sum(DB::raw('COALESCE(discount_amount, 0) + COALESCE(free_shipping_discount, 0)')),
            'shipping_revenue' => (float) (clone $paid)->sum('shipping_fee'),
            'net_order_revenue' => $net,
            'paid_orders' => $paidCount,
            'average_order_value' => $paidCount > 0 ? $net / $paidCount : 0.0,
            'refunded_orders' => $this->within(Order::query()->where('payment_status', 'refunded'), $period)->count(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function orders(array $period): array
    {
        $all = $this->within(Order::query(), $period);
        $total = (clone $all)->count();
        $statuses = ['processing', 'packed', 'shipped', 'delivered', 'cancelled'];
        $counts = (clone $all)->selectRaw('order_status, COUNT(*) as count')->groupBy('order_status')->pluck('count', 'order_status');
        $nonCancelled = max(0, $total - (int) ($counts['cancelled'] ?? 0));

        return [
            'total_orders' => $total,
            'paid_orders' => (clone $all)->where('payment_status', 'paid')->count(),
            'pending_payment_orders' => (clone $all)->where('payment_status', 'pending')->count(),
            'statuses' => collect($statuses)->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'fulfilment_rate' => $nonCancelled > 0 ? ((int) ($counts['delivered'] ?? 0) / $nonCancelled) * 100 : 0.0,
            'cancellation_rate' => $total > 0 ? ((int) ($counts['cancelled'] ?? 0) / $total) * 100 : 0.0,
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function payments(array $period): array
    {
        $stripe = $this->providerOrders('stripe', $period);
        $duitNow = $this->providerOrders('duitnow', $period);

        return [
            'stripe_revenue' => (float) (clone $stripe)->sum('total'),
            'stripe_orders' => (clone $stripe)->count(),
            'duitnow_revenue' => (float) (clone $duitNow)->sum('total'),
            'duitnow_orders' => (clone $duitNow)->count(),
            'pending_duitnow_receipts' => $this->receiptsForProvider('duitnow', 'pending', $period)->count(),
            'rejected_receipts' => $this->receiptsForProvider('duitnow', 'rejected', $period)->count(),
            'payment_failures' => $this->within(Order::query(), $period)
                ->where(fn (Builder $query) => $query->where('payment_status', 'failed')->orWhere('stripe_payment_status', 'failed'))
                ->count(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function products(array $period): array
    {
        $sales = $this->productSales($period);
        $soldIds = $sales->pluck('product_id')->filter()->all();
        $threshold = $this->lowStockThreshold();

        return [
            'top_by_units' => $sales->sortByDesc('units_sold')->take(5)->values(),
            'top_by_revenue' => $sales->sortByDesc('paid_revenue')->take(5)->values(),
            'lowest_selling' => $sales->sortBy('units_sold')->take(5)->values(),
            'no_sales' => Product::query()->where('status', 'active')->when($soldIds !== [], fn (Builder $query) => $query->whereNotIn('id', $soldIds))->orderBy('name')->limit(10)->get(['id', 'name', 'stock']),
            'low_stock' => Product::query()->where('stock', '>', 0)->where('stock', '<=', $threshold)->orderBy('stock')->limit(10)->get(['id', 'name', 'stock']),
            'out_of_stock' => Product::query()->where('stock', '<=', 0)->orderBy('name')->limit(10)->get(['id', 'name', 'stock']),
            'cost_data_available' => false,
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function customers(array $period): array
    {
        $email = $this->emailExpression();
        $periodOrders = $this->within(Order::query()->whereRaw($email." <> ''"), $period);
        $uniqueCustomers = (int) (clone $periodOrders)->selectRaw("COUNT(DISTINCT {$email}) as aggregate")->value('aggregate');
        $registered = (int) (clone $periodOrders)->whereExists(DB::table('users')->selectRaw('1')->whereRaw('LOWER(TRIM(users.email)) = '.$email))->selectRaw("COUNT(DISTINCT {$email}) as aggregate")->value('aggregate');
        $newCustomers = $this->newCustomers($period);
        $returning = $this->returningCustomers($period);
        $paidCustomerCount = (int) $this->within($this->paidNonCancelledOrders()->whereRaw($email." <> ''"), $period)->selectRaw("COUNT(DISTINCT {$email}) as aggregate")->value('aggregate');
        $paidRevenue = (float) $this->within($this->paidNonCancelledOrders(), $period)->sum('total');

        return [
            'unique_customers' => $uniqueCustomers,
            'new_customers' => $newCustomers,
            'returning_customers' => $returning,
            'registered_customers' => $registered,
            'guest_customers' => max(0, $uniqueCustomers - $registered),
            'average_customer_spend' => $paidCustomerCount > 0 ? $paidRevenue / $paidCustomerCount : 0.0,
            'repeat_purchase_rate' => $uniqueCustomers > 0 ? ($returning / $uniqueCustomers) * 100 : 0.0,
            'top_customers' => $this->topCustomers($period),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function couponSummary(array $period): array
    {
        $usageRows = $this->couponUsageRows($period);
        $mostUsed = $usageRows->sortByDesc('uses')->first();

        return [
            'active_coupons' => Coupon::query()->where('is_active', true)->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'total_uses' => (int) $usageRows->sum('uses'),
            'discount_issued' => (float) $usageRows->sum('discount_issued'),
            'most_used' => $mostUsed,
            'coupon_order_revenue' => (float) $this->within($this->paidNonCancelledOrders()->whereNotNull('coupon_code'), $period)->sum('total'),
            'expired_coupons' => Coupon::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
            'approaching_limits' => Coupon::query()->whereNotNull('usage_limit')->whereRaw('used_count >= usage_limit * 0.8')->orderByDesc('used_count')->limit(10)->get(['id', 'code', 'used_count', 'usage_limit']),
            'usage_rows' => $usageRows->take(10),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function inventorySummary(array $period): array
    {
        $threshold = $this->lowStockThreshold();

        return [
            'active_products' => Product::query()->where('status', 'active')->count(),
            'units_in_stock' => (int) Product::query()->sum('stock'),
            'low_stock_products' => Product::query()->where('stock', '>', 0)->where('stock', '<=', $threshold)->count(),
            'out_of_stock_products' => Product::query()->where('stock', '<=', 0)->count(),
            'threshold' => $threshold,
            'units_sold' => (int) $this->productSales($period)->sum('units_sold'),
            'units_restored' => (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->whereNotNull('orders.stock_restored_at')->whereBetween('orders.stock_restored_at', [$period['start'], $period['end']])->sum('order_items.quantity'),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function newsletterSummary(array $period): array
    {
        return [
            'active_subscribers' => NewsletterSubscriber::query()->subscribed()->count(),
            'new_subscribers' => NewsletterSubscriber::query()->where('status', 'subscribed')->whereBetween('subscribed_at', [$period['start'], $period['end']])->count(),
            'unsubscribed' => NewsletterSubscriber::query()->whereBetween('unsubscribed_at', [$period['start'], $period['end']])->count(),
            'total_subscribers' => NewsletterSubscriber::query()->count(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function revenueTrend(array $period): array
    {
        $rows = $this->within($this->paidNonCancelledOrders(), $period)
            ->selectRaw('DATE(created_at) as order_date, COALESCE(SUM(subtotal), 0) as gross, COALESCE(SUM(COALESCE(discount_amount, 0) + COALESCE(free_shipping_discount, 0)), 0) as discount, COALESCE(SUM(shipping_fee), 0) as shipping, COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->keyBy('order_date');
        $series = $this->datesInPeriod($period)->map(function (CarbonImmutable $date) use ($rows): array {
            $row = $rows->get($date->toDateString());

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('d M'),
                'gross' => (float) ($row->gross ?? 0),
                'discount' => (float) ($row->discount ?? 0),
                'shipping' => (float) ($row->shipping ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
                'order_count' => (int) ($row->order_count ?? 0),
            ];
        });

        return ['labels' => $series->pluck('label')->all(), 'revenue' => $series->pluck('revenue')->all(), 'rows' => $series];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function statusChart(array $period): array
    {
        $statuses = ['pending', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'];
        $counts = $this->within(Order::query(), $period)->whereIn('order_status', $statuses)->selectRaw('order_status, COUNT(*) as count')->groupBy('order_status')->pluck('count', 'order_status');

        return [
            'labels' => collect($statuses)->map(fn (string $status) => str($status)->replace('_', ' ')->title()->toString())->all(),
            'values' => collect($statuses)->map(fn (string $status) => (int) ($counts[$status] ?? 0))->all(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function paymentChart(array $period): array
    {
        return [
            'labels' => ['Stripe', 'DuitNow'],
            'values' => [
                (float) $this->providerOrders('stripe', $period)->sum('total'),
                (float) $this->providerOrders('duitnow', $period)->sum('total'),
            ],
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function productChart(array $period): array
    {
        $rows = $this->productSales($period)->sortByDesc('units_sold')->take(5)->values();

        return ['labels' => $rows->pluck('product_name')->all(), 'values' => $rows->pluck('units_sold')->map(fn ($value) => (int) $value)->all()];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function customerGrowth(array $period): array
    {
        $email = $this->emailExpression();
        $firstOrders = Order::query()->whereRaw($email." <> ''")->selectRaw($email.' as customer_email, MIN(created_at) as first_order_at')->groupByRaw($email);
        $rows = DB::query()->fromSub($firstOrders, 'first_orders')->whereBetween('first_order_at', [$period['start'], $period['end']])->selectRaw('DATE(first_order_at) as customer_date, COUNT(*) as count')->groupBy('customer_date')->pluck('count', 'customer_date');
        $series = $this->datesInPeriod($period)->map(fn (CarbonImmutable $date): array => ['label' => $date->format('d M'), 'value' => (int) ($rows[$date->toDateString()] ?? 0)]);

        return ['labels' => $series->pluck('label')->all(), 'values' => $series->pluck('value')->all()];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function newsletterGrowth(array $period): array
    {
        $rows = NewsletterSubscriber::query()->where('status', 'subscribed')->whereBetween('subscribed_at', [$period['start'], $period['end']])->selectRaw('DATE(subscribed_at) as subscriber_date, COUNT(*) as count')->groupBy('subscriber_date')->pluck('count', 'subscriber_date');
        $series = $this->datesInPeriod($period)->map(fn (CarbonImmutable $date): array => ['label' => $date->format('d M'), 'value' => (int) ($rows[$date->toDateString()] ?? 0)]);

        return ['labels' => $series->pluck('label')->all(), 'values' => $series->pluck('value')->all()];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function productSales(array $period): Collection
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->where(fn ($query) => $query->whereNull('orders.order_status')->orWhere('orders.order_status', '!=', 'cancelled'))
            ->whereBetween('orders.created_at', [$period['start'], $period['end']])
            ->selectRaw('order_items.product_id, MAX(COALESCE(order_items.product_name, order_items.name)) as product_name, SUM(order_items.quantity) as units_sold, SUM(COALESCE(order_items.line_total, order_items.total, 0)) as paid_revenue')
            ->groupBy('order_items.product_id')
            ->get();
        $products = Product::query()->whereIn('id', $rows->pluck('product_id')->filter()->all())->get(['id', 'name', 'stock'])->keyBy('id');

        return $rows->map(function (object $row) use ($products): object {
            $row->product_name = $products->get($row->product_id)?->name ?: $row->product_name ?: 'Cherry Bellemont item';
            $row->stock = $products->get($row->product_id)?->stock;
            $row->units_sold = (int) $row->units_sold;
            $row->paid_revenue = (float) $row->paid_revenue;

            return $row;
        });
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function topCustomers(array $period): Collection
    {
        $email = $this->emailExpression();

        return $this->within($this->paidNonCancelledOrders()->whereRaw($email." <> ''"), $period)
            ->selectRaw($email.' as customer_email, MAX(COALESCE(customer_name, full_name)) as customer_name, COUNT(*) as paid_orders, SUM(total) as total_spent')
            ->groupByRaw($email)
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(function (object $customer): object {
                $customer->paid_orders = (int) $customer->paid_orders;
                $customer->total_spent = (float) $customer->total_spent;

                return $customer;
            });
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function couponUsageRows(array $period): Collection
    {
        return CouponUsage::query()
            ->leftJoin('coupons', 'coupons.id', '=', 'coupon_usages.coupon_id')
            ->whereBetween('coupon_usages.used_at', [$period['start'], $period['end']])
            ->selectRaw('coupon_usages.coupon_id, MAX(coupons.code) as code, COUNT(*) as uses, COALESCE(SUM(coupon_usages.discount_amount), 0) as discount_issued')
            ->groupBy('coupon_usages.coupon_id')
            ->orderByDesc('uses')
            ->get()
            ->map(function (object $coupon): object {
                $coupon->uses = (int) $coupon->uses;
                $coupon->discount_issued = (float) $coupon->discount_issued;

                return $coupon;
            });
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function newCustomers(array $period): int
    {
        $email = $this->emailExpression();

        return Order::query()->whereRaw($email." <> ''")->selectRaw($email.' as customer_email, MIN(created_at) as first_order_at')->groupByRaw($email)->havingBetween('first_order_at', [$period['start'], $period['end']])->get()->count();
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function returningCustomers(array $period): int
    {
        $email = $this->emailExpression();

        return Order::query()->whereRaw($email." <> ''")->selectRaw($email.' as customer_email')->groupByRaw($email)->havingRaw('COUNT(*) > 1')->havingRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) > 0', [$period['start'], $period['end']])->get()->count();
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function providerOrders(string $provider, array $period): Builder
    {
        return $this->applyProvider($this->within($this->paidNonCancelledOrders(), $period), $provider);
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function receiptsForProvider(string $provider, string $status, array $period): Builder
    {
        return PaymentReceipt::query()
            ->where('status', $status)
            ->whereBetween('submitted_at', [$period['start'], $period['end']])
            ->whereHas('order', fn (Builder $query) => $this->applyProvider($query, $provider));
    }

    private function paidNonCancelledOrders(): Builder
    {
        return Order::query()->where('payment_status', 'paid')->where(fn (Builder $query) => $query->whereNull('order_status')->orWhere('order_status', '!=', 'cancelled'));
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function within(Builder $query, array $period): Builder
    {
        return $query->whereBetween('created_at', [$period['start'], $period['end']]);
    }

    private function applyProvider(Builder $query, string $provider): Builder
    {
        return $query->where(function (Builder $providerQuery) use ($provider): void {
            $providerQuery->where('payment_provider', $provider)->orWhere(fn (Builder $legacy) => $legacy->whereNull('payment_provider')->where('payment_method', $provider));
        });
    }

    private function emailExpression(): string
    {
        return 'LOWER(TRIM(COALESCE(customer_email, email)))';
    }

    private function lowStockThreshold(): int
    {
        return max(0, (int) config('store.low_stock_threshold', 3));
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function datesInPeriod(array $period): Collection
    {
        $dates = collect();
        $date = $period['start']->startOfDay();

        while ($date->lessThanOrEqualTo($period['end']->startOfDay())) {
            $dates->push($date);
            $date = $date->addDay();
        }

        return $dates;
    }
}
