<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-16 12:00:00');
        config(['store.low_stock_threshold' => 3]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_paid_non_cancelled_orders_count_toward_revenue(): void
    {
        $this->order(['payment_status' => 'paid', 'order_status' => 'processing', 'total' => '100.00']);
        $this->order(['payment_status' => 'pending', 'order_status' => 'pending', 'total' => '500.00']);
        $this->order(['payment_status' => 'paid', 'order_status' => 'cancelled', 'total' => '200.00']);
        $this->order(['payment_status' => 'refunded', 'order_status' => 'delivered', 'total' => '300.00']);

        $dashboard = app(AdminAnalyticsService::class)->dashboard(['range' => 'today']);

        $this->assertSame(100.0, $dashboard['revenue_chart']['revenue'][0]);
        $this->assertSame('RM 100.00', $this->summary($dashboard, 'Revenue Today')['value']);
    }

    public function test_pending_receipt_and_paid_awaiting_processing_metrics_are_separate(): void
    {
        $paidPending = $this->order(['payment_status' => 'paid', 'order_status' => 'pending', 'payment_method' => 'duitnow', 'payment_provider' => 'duitnow']);
        $this->order(['payment_status' => 'paid', 'order_status' => 'processing']);
        $stripeOrder = $this->order(['payment_status' => 'pending', 'order_status' => 'pending', 'payment_method' => 'stripe', 'payment_provider' => 'stripe']);

        PaymentReceipt::create(['order_id' => $paidPending->id, 'path' => 'payment-receipts/duitnow.jpg', 'status' => 'pending', 'submitted_at' => now()]);
        PaymentReceipt::create(['order_id' => $stripeOrder->id, 'path' => 'payment-receipts/stripe.jpg', 'status' => 'pending', 'submitted_at' => now()]);

        $dashboard = app(AdminAnalyticsService::class)->dashboard(['range' => 'today']);

        $this->assertSame(1, $this->summary($dashboard, 'Paid Awaiting Processing')['value']);
        $this->assertSame(1, $this->summary($dashboard, 'Pending DuitNow Receipts')['value']);
    }

    public function test_stripe_and_duitnow_paid_totals_are_separated(): void
    {
        $this->order(['payment_status' => 'paid', 'order_status' => 'processing', 'payment_method' => 'stripe', 'payment_provider' => 'stripe', 'total' => '80.00']);
        $this->order(['payment_status' => 'paid', 'order_status' => 'processing', 'payment_method' => 'duitnow', 'payment_provider' => 'duitnow', 'total' => '40.00']);
        $this->order(['payment_status' => 'pending', 'order_status' => 'pending', 'payment_method' => 'stripe', 'payment_provider' => 'stripe', 'total' => '120.00']);

        $overview = app(AdminAnalyticsService::class)->dashboard(['range' => 'today'])['payment_overview'];

        $this->assertSame(80.0, $overview['stripe_revenue']);
        $this->assertSame(40.0, $overview['duitnow_revenue']);
        $this->assertSame(1, $overview['stripe_orders']);
        $this->assertSame(1, $overview['duitnow_orders']);
    }

    public function test_top_products_include_only_paid_non_cancelled_order_quantities(): void
    {
        $topProduct = Product::create($this->productData(['name' => 'Top Piece', 'stock' => 8]));
        $cancelledProduct = Product::create($this->productData(['name' => 'Cancelled Piece', 'stock' => 8]));
        $paidOrder = $this->order(['payment_status' => 'paid', 'order_status' => 'processing']);
        $cancelledOrder = $this->order(['payment_status' => 'paid', 'order_status' => 'cancelled']);

        $paidOrder->items()->create(['product_id' => $topProduct->id, 'name' => $topProduct->name, 'product_name' => $topProduct->name, 'quantity' => 2, 'unit_price' => '50.00', 'total' => '100.00', 'line_total' => '100.00']);
        $cancelledOrder->items()->create(['product_id' => $cancelledProduct->id, 'name' => $cancelledProduct->name, 'product_name' => $cancelledProduct->name, 'quantity' => 9, 'unit_price' => '50.00', 'total' => '450.00', 'line_total' => '450.00']);

        $products = app(AdminAnalyticsService::class)->dashboard(['range' => 'today'])['top_products'];

        $this->assertCount(1, $products);
        $this->assertSame($topProduct->id, $products->first()['product']->id);
        $this->assertSame(2, $products->first()['units_sold']);
    }

    public function test_low_stock_list_uses_the_configured_threshold(): void
    {
        $atThreshold = Product::create($this->productData(['name' => 'At Threshold', 'stock' => 3]));
        $outOfStock = Product::create($this->productData(['name' => 'Out Of Stock', 'stock' => 0]));
        Product::create($this->productData(['name' => 'Healthy Stock', 'stock' => 4]));

        $dashboard = app(AdminAnalyticsService::class)->dashboard(['range' => 'today']);
        $ids = $dashboard['low_stock_products']->pluck('id')->all();

        $this->assertSame(3, $dashboard['low_stock_threshold']);
        $this->assertContains($atThreshold->id, $ids);
        $this->assertContains($outOfStock->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_non_admin_cannot_access_dashboard_analytics(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_date_filters_apply_to_revenue_and_fulfilment_charts(): void
    {
        $this->order(['payment_status' => 'paid', 'order_status' => 'processing', 'total' => '50.00', 'created_at' => now()->subDays(2)]);
        $this->order(['payment_status' => 'paid', 'order_status' => 'shipped', 'total' => '20.00', 'created_at' => now()->subDays(8)]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['range' => 'last_7_days']));

        $response->assertOk()
            ->assertViewHas('dashboard', function (array $dashboard): bool {
                return array_sum($dashboard['revenue_chart']['revenue']) === 50.0
                    && $dashboard['status_chart']['values'][1] === 1
                    && $dashboard['status_chart']['values'][3] === 0;
            });

        Cache::flush();
        $this->actingAs($admin)->get(route('admin.dashboard', [
            'range' => 'custom',
            'from_date' => now()->subDays(9)->toDateString(),
            'to_date' => now()->subDays(7)->toDateString(),
        ]))->assertOk()
            ->assertViewHas('dashboard', fn (array $dashboard): bool => array_sum($dashboard['revenue_chart']['revenue']) === 20.0);
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-DASH-'.Str::upper(Str::random(8));
        $createdAt = $attributes['created_at'] ?? now();
        unset($attributes['created_at']);

        $order = Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Dashboard Customer',
            'customer_email' => 'dashboard@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Dashboard Street',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Dashboard Street'],
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00',
            'shipping_fee' => '0.00',
            'total' => '100.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $attributes));

        $order->timestamps = false;
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        $order->timestamps = true;

        return $order->fresh();
    }

    private function productData(array $attributes = []): array
    {
        $name = $attributes['name'] ?? 'Dashboard Piece '.Str::random(6);

        return array_merge([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => 'A dashboard test piece.',
            'price' => '50.00',
            'stock' => 5,
            'status' => 'active',
        ], $attributes);
    }

    private function summary(array $dashboard, string $label): array
    {
        return collect($dashboard['summary_cards'])->firstWhere('label', $label);
    }
}
