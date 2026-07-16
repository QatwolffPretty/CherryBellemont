<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminCustomerService;
use App\Services\AdminReportsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCustomersAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-17 12:00:00');
        config(['store.low_stock_threshold' => 3]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_orders_are_grouped_by_normalized_email_and_registered_customers_are_distinguished(): void
    {
        $registered = User::factory()->create(['email' => 'customer@example.test', 'is_admin' => false]);
        $this->order(['customer_email' => 'Customer@Example.test', 'customer_name' => 'Guest Name', 'payment_status' => 'paid', 'total' => '100.00']);
        $this->order(['customer_email' => ' customer@example.test ', 'customer_name' => 'Guest Name', 'payment_status' => 'paid', 'order_status' => 'cancelled', 'total' => '50.00']);
        $this->order(['customer_email' => 'guest@example.test', 'customer_name' => 'Guest Only', 'payment_status' => 'pending', 'total' => '30.00']);

        $customers = app(AdminCustomerService::class)->exportRows(['search' => null, 'filter' => 'all'])->keyBy('customer_email');

        $this->assertCount(2, $customers);
        $this->assertTrue($customers['customer@example.test']->registered);
        $this->assertSame($registered->id, $customers['customer@example.test']->user->id);
        $this->assertSame(2, $customers['customer@example.test']->total_orders);
        $this->assertSame(1, $customers['customer@example.test']->paid_orders);
        $this->assertSame(100.0, $customers['customer@example.test']->total_spent);
        $this->assertFalse($customers['guest@example.test']->registered);
    }

    public function test_customer_detail_includes_matching_order_history_and_internal_notes_stay_admin_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = $this->order(['customer_email' => 'detail@example.test', 'customer_name' => 'Detail Customer']);
        $second = $this->order(['customer_email' => 'DETAIL@example.test', 'customer_name' => 'Detail Customer']);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', ['email' => 'detail@example.test']));

        $response->assertOk()->assertSee($first->order_number)->assertSee($second->order_number);
        $this->actingAs($admin)->post(route('admin.customers.notes.store', ['email' => 'detail@example.test']), ['note' => 'Prefers afternoon delivery.'])->assertSessionHas('success');
        $this->assertDatabaseHas('customer_notes', ['customer_email' => 'detail@example.test', 'admin_id' => $admin->id]);
    }

    public function test_customer_csv_export_respects_the_paid_customer_filter_and_non_admins_are_blocked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);
        $this->order(['customer_email' => 'paid@example.test', 'payment_status' => 'paid']);
        $this->order(['customer_email' => 'pending@example.test', 'payment_status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('admin.customers.export', ['filter' => 'paid']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('paid@example.test', $csv);
        $this->assertStringNotContainsString('pending@example.test', $csv);
        $this->actingAs($customer)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_admin_can_open_the_customers_list_and_reports_overview(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->order(['customer_email' => 'overview@example.test', 'payment_status' => 'paid']);

        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => 'overview']))
            ->assertOk()
            ->assertSee('overview@example.test');
        $this->actingAs($admin)->get(route('admin.reports.index', ['range' => 'today']))
            ->assertOk()
            ->assertSee('Gross paid revenue')
            ->assertSee('Top customers by paid spend');
    }

    public function test_reports_count_only_paid_non_cancelled_sales_and_keep_providers_separate(): void
    {
        $this->order(['payment_status' => 'paid', 'payment_provider' => 'stripe', 'payment_method' => 'stripe', 'subtotal' => '100.00', 'shipping_fee' => '8.00', 'total' => '108.00']);
        $this->order(['payment_status' => 'paid', 'payment_provider' => 'duitnow', 'payment_method' => 'duitnow', 'subtotal' => '40.00', 'shipping_fee' => '0.00', 'total' => '40.00']);
        $this->order(['payment_status' => 'paid', 'order_status' => 'cancelled', 'payment_provider' => 'stripe', 'total' => '90.00']);
        $this->order(['payment_status' => 'pending', 'payment_provider' => 'stripe', 'total' => '70.00']);

        $report = app(AdminReportsService::class)->report(['range' => 'today', 'from_date' => null, 'to_date' => null]);

        $this->assertSame(148.0, $report['sales']['net_order_revenue']);
        $this->assertSame(108.0, $report['payments']['stripe_revenue']);
        $this->assertSame(40.0, $report['payments']['duitnow_revenue']);
    }

    public function test_reports_use_paid_product_sales_normalized_customer_counts_coupon_usage_and_newsletter_metrics(): void
    {
        $product = Product::create($this->productData(['stock' => 2]));
        $paid = $this->order(['customer_email' => 'Buyer@Example.test', 'payment_status' => 'paid', 'total' => '100.00']);
        $cancelled = $this->order(['customer_email' => ' buyer@example.test ', 'payment_status' => 'paid', 'order_status' => 'cancelled', 'total' => '80.00']);
        $paid->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => '50.00', 'line_total' => '100.00', 'total' => '100.00']);
        $cancelled->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 9, 'unit_price' => '50.00', 'line_total' => '450.00', 'total' => '450.00']);
        $coupon = Coupon::create(['code' => 'WELCOME', 'name' => 'Welcome', 'type' => 'percentage', 'value' => '10.00', 'is_active' => true, 'used_count' => 1]);
        CouponUsage::create(['coupon_id' => $coupon->id, 'order_id' => $paid->id, 'customer_email' => 'buyer@example.test', 'discount_amount' => '10.00', 'used_at' => now()]);
        NewsletterSubscriber::create(['email' => 'buyer@example.test', 'status' => 'subscribed', 'subscribed_at' => now(), 'source' => 'footer']);

        $report = app(AdminReportsService::class)->report(['range' => 'today', 'from_date' => null, 'to_date' => null]);

        $this->assertSame(2, $report['products']['top_by_units']->first()->units_sold);
        $this->assertSame(1, $report['customers']['unique_customers']);
        $this->assertSame(1, $report['coupons']['total_uses']);
        $this->assertSame(10.0, $report['coupons']['discount_issued']);
        $this->assertSame(1, $report['newsletter']['new_subscribers']);
        $this->assertSame(1, $report['inventory']['low_stock_products']);
    }

    public function test_reports_date_filter_and_csv_export_respect_the_selected_period(): void
    {
        $today = $this->order(['payment_status' => 'paid', 'total' => '40.00']);
        $older = $this->order(['payment_status' => 'paid', 'total' => '90.00']);
        $older->timestamps = false;
        $older->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();
        $older->timestamps = true;
        $admin = User::factory()->create(['is_admin' => true]);

        $report = app(AdminReportsService::class)->report(['range' => 'today', 'from_date' => null, 'to_date' => null]);
        $this->assertSame(40.0, $report['sales']['net_order_revenue']);

        $response = $this->actingAs($admin)->get(route('admin.reports.export', ['report' => 'orders', 'range' => 'today']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($today->order_number, $csv);
        $this->assertStringNotContainsString($older->order_number, $csv);
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.reports.index'))->assertForbidden();
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-REPORT-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Report Customer',
            'customer_email' => 'report-'.Str::lower(Str::random(8)).'@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Report Street',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Report Street'],
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
    }

    private function productData(array $attributes = []): array
    {
        $name = $attributes['name'] ?? 'Report Piece '.Str::random(6);

        return array_merge([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => 'A reporting test piece.',
            'price' => '50.00',
            'stock' => 5,
            'status' => 'active',
        ], $attributes);
    }
}
