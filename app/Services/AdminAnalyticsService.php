<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterCampaign;
use App\Models\ProductStockNotification;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\Review;
use App\Models\Refund;
use App\Models\ReturnRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
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

    /**
     * @param array{range?: string, from_date?: ?string, to_date?: ?string} $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $period = $this->period($filters);
        $aggregateKey = 'admin-dashboard:aggregates:'.sha1($period['key'].'|'.$period['start']->toDateString().'|'.$period['end']->toDateString());

        $aggregates = Cache::remember($aggregateKey, now()->addMinutes(2), function () use ($period): array {
            return [
                'summary_cards' => $this->summaryCards(),
                'revenue_chart' => $this->revenueChart($period),
                'status_chart' => $this->statusChart($period),
                'payment_overview' => $this->paymentOverview($period),
            ];
        });

        return [
            ...$aggregates,
            'period' => [
                'key' => $period['key'],
                'label' => $period['label'],
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
            ],
            'recent_orders' => $this->recentOrders(),
            'top_products' => $this->topProducts(),
            'low_stock_products' => $this->lowStockProducts(),
            'activity' => $this->recentActivity(),
            'low_stock_threshold' => $this->lowStockThreshold(),
        ];
    }

    /**
     * @param array{range?: string, from_date?: ?string, to_date?: ?string} $filters
     * @return array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function period(array $filters): array
    {
        $range = $filters['range'] ?? 'last_7_days';
        $today = CarbonImmutable::now();

        return match ($range) {
            'today' => $this->periodResult('today', 'Today', $today->startOfDay(), $today->endOfDay()),
            'last_30_days' => $this->periodResult('last_30_days', 'Last 30 Days', $today->subDays(29)->startOfDay(), $today->endOfDay()),
            'this_month' => $this->periodResult('this_month', 'This Month', $today->startOfMonth(), $today->endOfDay()),
            'this_year' => $this->periodResult('this_year', 'This Year', $today->startOfYear(), $today->endOfDay()),
            'custom' => $this->periodResult(
                'custom',
                'Custom Range',
                CarbonImmutable::parse((string) $filters['from_date'])->startOfDay(),
                CarbonImmutable::parse((string) $filters['to_date'])->endOfDay(),
            ),
            default => $this->periodResult('last_7_days', 'Last 7 Days', $today->subDays(6)->startOfDay(), $today->endOfDay()),
        };
    }

    /** @return array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable} */
    private function periodResult(string $key, string $label, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return compact('key', 'label', 'start', 'end');
    }

    /** @return array<int, array{label: string, value: string|int, subtitle?: string, href: string, accent?: bool}> */
    private function summaryCards(): array
    {
        $today = CarbonImmutable::now();
        $monthStart = $today->startOfMonth();
        $threshold = $this->lowStockThreshold();

        return [
            ['label' => 'Revenue Today', 'value' => $this->currency($this->paidRevenue($today->startOfDay(), $today->endOfDay())), 'href' => route('admin.reports.index')],
            ['label' => 'Revenue This Month', 'value' => $this->currency($this->paidRevenue($monthStart, $today->endOfDay())), 'href' => route('admin.reports.index')],
            ['label' => 'Orders Today', 'value' => Order::query()->whereBetween('created_at', [$today->startOfDay(), $today->endOfDay()])->count(), 'href' => route('admin.orders.index')],
            ['label' => 'Pending Orders', 'value' => Order::query()->where('order_status', 'pending')->count(), 'href' => route('admin.orders.index', ['order_status' => 'pending'])],
            ['label' => 'Paid Awaiting Processing', 'value' => Order::query()->where('payment_status', 'paid')->where('order_status', 'pending')->count(), 'href' => route('admin.orders.index', ['payment_status' => 'paid', 'order_status' => 'pending'])],
            ['label' => 'Pending DuitNow Receipts', 'value' => $this->pendingDuitNowReceipts(), 'href' => route('admin.payment-receipts.index', ['status' => 'pending']), 'accent' => true],
            ['label' => 'Orders Processing', 'value' => Order::query()->where('order_status', 'processing')->count(), 'href' => route('admin.orders.index', ['order_status' => 'processing'])],
            ['label' => 'Orders Shipped', 'value' => Order::query()->where('order_status', 'shipped')->count(), 'href' => route('admin.orders.index', ['order_status' => 'shipped'])],
            ['label' => 'Low Stock Products', 'value' => Product::query()->where('stock', '<=', $threshold)->count(), 'subtitle' => $threshold.' or fewer remaining', 'href' => route('admin.products.index', ['low_stock' => 1])],
            ['label' => 'Back-in-Stock Requests', 'value' => ProductStockNotification::query()->waiting()->count(), 'href' => route('admin.product-stock-notifications.index', ['status' => 'waiting'])],
            ['label' => 'Products With Waiting Customers', 'value' => ProductStockNotification::query()->waiting()->distinct('product_id')->count('product_id'), 'href' => route('admin.product-stock-notifications.index', ['status' => 'waiting'])],
            ['label' => 'Most Requested Out-of-Stock Product', 'value' => $this->mostRequestedOutOfStockProduct(), 'href' => route('admin.product-stock-notifications.index', ['status' => 'waiting'])],
            ['label' => 'Active Coupons', 'value' => $this->activeCoupons(), 'href' => route('admin.coupons.index', ['status' => 'active'])],
            ['label' => 'Newsletter Subscribers', 'value' => NewsletterSubscriber::query()->subscribed()->count(), 'href' => route('admin.newsletter.index', ['status' => 'subscribed'])],
            ['label' => 'Draft Campaigns', 'value' => NewsletterCampaign::query()->drafts()->count(), 'href' => route('admin.newsletter.campaigns.index', ['status' => 'draft'])],
            ['label' => 'Scheduled Campaigns', 'value' => NewsletterCampaign::query()->scheduled()->count(), 'href' => route('admin.newsletter.campaigns.index', ['status' => 'scheduled'])],
            ['label' => 'Sent Campaigns', 'value' => NewsletterCampaign::query()->sent()->count(), 'href' => route('admin.newsletter.campaigns.index', ['status' => 'sent'])],
            ['label' => 'Latest Campaign', 'value' => $this->latestCampaignName(), 'href' => route('admin.newsletter.campaigns.index')],
            ['label' => 'Pending Reviews', 'value' => Review::query()->where('status', 'pending')->count(), 'href' => route('admin.reviews.index', ['status' => 'pending']), 'accent' => true],
            ['label' => 'Pending Returns', 'value' => ReturnRequest::query()->whereIn('status', ['requested', 'under_review'])->count(), 'href' => route('admin.returns.index', ['status' => 'requested']), 'accent' => true],
            ['label' => 'Returns Awaiting Inspection', 'value' => ReturnRequest::query()->whereIn('status', ['item_received', 'inspecting'])->count(), 'href' => route('admin.returns.index', ['status' => 'inspecting'])],
            ['label' => 'Refunds Processing', 'value' => Refund::query()->whereIn('status', ['pending', 'processing'])->count(), 'href' => route('admin.returns.index')],
            ['label' => 'Refunds Failed', 'value' => Refund::query()->where('status', 'failed')->count(), 'href' => route('admin.returns.index')],
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function revenueChart(array $period): array
    {
        $rows = $this->within($this->paidOrders(), $period)
            ->selectRaw('DATE(created_at) as order_date, COALESCE(SUM(total), 0) as revenue, COUNT(*) as order_count')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->keyBy('order_date');

        $dates = $this->datesInPeriod($period);
        $series = $dates->map(function (CarbonImmutable $date) use ($rows, $period): array {
            $row = $rows->get($date->toDateString());

            return [
                'label' => $period['start']->diffInDays($period['end']) > 90 ? $date->format('M Y') : $date->format('d M'),
                'date' => $date->toDateString(),
                'revenue' => (float) ($row->revenue ?? 0),
                'order_count' => (int) ($row->order_count ?? 0),
            ];
        })->values();

        return [
            'labels' => $series->pluck('label')->all(),
            'revenue' => $series->pluck('revenue')->all(),
            'order_counts' => $series->pluck('order_count')->all(),
            'rows' => $series->all(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function statusChart(array $period): array
    {
        $statuses = ['pending', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'];
        $counts = Order::query()
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->whereIn('order_status', $statuses)
            ->selectRaw('order_status, COUNT(*) as order_count')
            ->groupBy('order_status')
            ->pluck('order_count', 'order_status');

        return [
            'labels' => collect($statuses)->map(fn (string $status) => str($status)->replace('_', ' ')->title()->toString())->all(),
            'values' => collect($statuses)->map(fn (string $status) => (int) ($counts[$status] ?? 0))->all(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function paymentOverview(array $period): array
    {
        return [
            'stripe_revenue' => $this->paidProviderRevenue('stripe', $period),
            'duitnow_revenue' => $this->paidProviderRevenue('duitnow', $period),
            'stripe_orders' => $this->paidProviderOrders('stripe', $period)->count(),
            'duitnow_orders' => $this->paidProviderOrders('duitnow', $period)->count(),
            'pending_duitnow_receipts' => $this->pendingDuitNowReceipts(),
        ];
    }

    private function recentOrders(): Collection
    {
        return Order::query()
            ->select(['id', 'number', 'order_number', 'customer_name', 'customer_email', 'total', 'payment_method', 'payment_provider', 'payment_status', 'order_status', 'shipping_method_name', 'created_at'])
            ->latest()
            ->limit(10)
            ->get();
    }

    private function topProducts(): Collection
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->where(function ($query): void {
                $query->whereNull('orders.order_status')->orWhere('orders.order_status', '!=', 'cancelled');
            })
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id, MAX(COALESCE(order_items.product_name, order_items.name)) as product_name, SUM(order_items.quantity) as units_sold, SUM(COALESCE(order_items.line_total, order_items.total, 0)) as paid_revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('units_sold')
            ->orderByDesc('paid_revenue')
            ->limit(5)
            ->get();

        $products = Product::query()
            ->whereIn('id', $rows->pluck('product_id')->filter()->all())
            ->get(['id', 'name', 'image_path', 'stock'])
            ->keyBy('id');

        return $rows->map(function (object $row) use ($products): array {
            return [
                'product' => $products->get($row->product_id),
                'name' => $row->product_name ?: 'Cherry Bellemont item',
                'units_sold' => (int) $row->units_sold,
                'paid_revenue' => (float) $row->paid_revenue,
            ];
        });
    }

    private function lowStockProducts(): Collection
    {
        return Product::query()
            ->select(['id', 'name', 'image_path', 'stock'])
            ->where('stock', '<=', $this->lowStockThreshold())
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    private function recentActivity(): Collection
    {
        $activity = collect();

        Order::query()->latest()->limit(8)->get(['id', 'number', 'order_number', 'created_at'])->each(function (Order $order) use ($activity): void {
            $activity->push($this->activity('bi-handbag-fill', 'New order placed', $this->orderNumber($order), $order->created_at));
        });

        PaymentReceipt::query()->with('order:id,number,order_number')->latest('submitted_at')->limit(8)->get()->each(function (PaymentReceipt $receipt) use ($activity): void {
            $activity->push($this->activity('bi-receipt', 'DuitNow receipt submitted', $this->orderNumber($receipt->order), $receipt->submitted_at ?: $receipt->created_at));
            if ($receipt->reviewed_at) {
                $activity->push($this->activity('bi-check2-circle', 'DuitNow receipt '.($receipt->status === 'approved' ? 'approved' : 'rejected'), $this->orderNumber($receipt->order), $receipt->reviewed_at));
            }
        });

        $this->applyProvider(Order::query()->where('payment_status', 'paid')->whereNotNull('stripe_paid_at'), 'stripe')
            ->latest('stripe_paid_at')->limit(8)->get(['id', 'number', 'order_number', 'stripe_paid_at'])
            ->each(function (Order $order) use ($activity): void {
                $activity->push($this->activity('bi-credit-card', 'Stripe payment confirmed', $this->orderNumber($order), $order->stripe_paid_at));
            });

        Order::query()->whereNotNull('shipped_at')->latest('shipped_at')->limit(6)->get(['id', 'number', 'order_number', 'shipped_at'])->each(function (Order $order) use ($activity): void {
            $activity->push($this->activity('bi-truck', 'Order shipped', $this->orderNumber($order), $order->shipped_at));
        });
        Order::query()->whereNotNull('delivered_at')->latest('delivered_at')->limit(6)->get(['id', 'number', 'order_number', 'delivered_at'])->each(function (Order $order) use ($activity): void {
            $activity->push($this->activity('bi-box2-heart', 'Order delivered', $this->orderNumber($order), $order->delivered_at));
        });
        Review::query()->with('product:id,name')->latest()->limit(6)->get()->each(function (Review $review) use ($activity): void {
            $activity->push($this->activity('bi-chat-square-quote', 'New review submitted', $review->product?->name ?: 'Product review', $review->created_at));
        });
        NewsletterSubscriber::query()->subscribed()->latest('subscribed_at')->limit(6)->get()->each(function (NewsletterSubscriber $subscriber) use ($activity): void {
            $activity->push($this->activity('bi-envelope-paper', 'New newsletter subscriber', 'Cherry Bellemont list', $subscriber->subscribed_at ?: $subscriber->created_at));
        });
        ReturnRequest::query()->latest('requested_at')->limit(6)->get()->each(function (ReturnRequest $return) use ($activity): void {
            $activity->push($this->activity('bi-arrow-repeat', 'Return request submitted', $return->return_number, $return->requested_at ?: $return->created_at));
        });
        Refund::query()->where('status', 'succeeded')->latest('confirmed_at')->limit(6)->get()->each(function (Refund $refund) use ($activity): void {
            $activity->push($this->activity('bi-arrow-counterclockwise', 'Refund confirmed', $refund->refund_number, $refund->confirmed_at ?: $refund->updated_at));
        });

        return $activity
            ->filter(fn (array $item) => $item['at'] !== null)
            ->sortByDesc(fn (array $item) => $item['at']->getTimestamp())
            ->take(12)
            ->values();
    }

    /** @return array{icon: string, title: string, detail: string, at: mixed} */
    private function activity(string $icon, string $title, string $detail, mixed $at): array
    {
        return compact('icon', 'title', 'detail', 'at');
    }

    private function paidRevenue(CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) $this->within($this->paidOrders(), compact('start', 'end'))->sum('total');
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function paidProviderRevenue(string $provider, array $period): float
    {
        return (float) $this->paidProviderOrders($provider, $period)->sum('total');
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function paidProviderOrders(string $provider, array $period): Builder
    {
        return $this->applyProvider($this->within($this->paidOrders(), $period), $provider);
    }

    private function paidOrders(): Builder
    {
        return Order::query()
            ->where('payment_status', 'paid')
            ->where(function (Builder $query): void {
                $query->whereNull('order_status')->orWhere('order_status', '!=', 'cancelled');
            });
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function within(Builder $query, array $period): Builder
    {
        return $query->whereBetween('created_at', [$period['start'], $period['end']]);
    }

    private function applyProvider(Builder $query, string $provider): Builder
    {
        return $query->where(function (Builder $providerQuery) use ($provider): void {
            $providerQuery->where('payment_provider', $provider)
                ->orWhere(function (Builder $legacyQuery) use ($provider): void {
                    $legacyQuery->whereNull('payment_provider')->where('payment_method', $provider);
                });
        });
    }

    private function pendingDuitNowReceipts(): int
    {
        return PaymentReceipt::query()
            ->where('status', 'pending')
            ->whereHas('order', fn (Builder $query) => $this->applyProvider($query, 'duitnow'))
            ->count();
    }

    private function activeCoupons(): int
    {
        return Coupon::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
    }

    private function latestCampaignName(): string
    {
        return NewsletterCampaign::query()->latest()->value('name') ?: 'No campaigns';
    }

    private function mostRequestedOutOfStockProduct(): string
    {
        $row = ProductStockNotification::query()
            ->join('products', 'products.id', '=', 'product_stock_notifications.product_id')
            ->where('product_stock_notifications.status', ProductStockNotification::STATUS_WAITING)
            ->where('products.stock', '<=', 0)
            ->selectRaw('product_stock_notifications.product_id, COUNT(*) as request_count')
            ->groupBy('product_stock_notifications.product_id')
            ->orderByDesc('request_count')
            ->first();

        return $row ? Product::query()->whereKey($row->product_id)->value('name') ?: 'Unavailable product' : 'No requests';
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

    private function currency(float $amount): string
    {
        return 'RM '.number_format($amount, 2);
    }

    private function orderNumber(?Order $order): string
    {
        return $order?->order_number ?: $order?->number ?: 'Order update';
    }
}
