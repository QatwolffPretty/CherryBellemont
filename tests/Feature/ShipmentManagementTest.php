<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Notifications\OrderCustomerNotification;
use App\Services\AdminReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Tests\TestCase;

class ShipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_couriers_while_customers_cannot(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.couriers.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.couriers.store'), [
            'name' => 'J&T Express', 'code' => 'JT_EXPRESS', 'tracking_url_template' => 'https://tracking.example.test/{tracking_number}',
            'website_url' => 'https://example.test', 'sort_order' => 10, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('couriers', ['code' => 'JT_EXPRESS', 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.couriers.index'))->assertOk()->assertSee('J&T Express');
    }

    public function test_paid_packed_order_can_create_and_ship_a_single_shipment_without_stock_change(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $product = $this->product(['stock' => 7]);
        $order = $this->order(['payment_status' => 'paid', 'order_status' => 'packed']);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => '50.00', 'total' => '100.00', 'line_total' => '100.00']);
        $courier = $this->courier();

        $this->actingAs($admin)->get(route('admin.orders.shipments.create', $order))->assertOk()->assertSee('Create Shipment');

        $this->actingAs($admin)->post(route('admin.orders.shipments.store', $order), [
            'courier_id' => $courier->id, 'tracking_number' => 'CB-TRACK-001', 'service_name' => 'Express',
        ])->assertRedirect();
        $shipment = Shipment::query()->firstOrFail();
        $this->assertSame('ready', $shipment->shipment_status);
        $this->actingAs($admin)->get(route('admin.shipments.index'))->assertOk()->assertSee($shipment->shipment_number);
        $this->actingAs($admin)->get(route('admin.shipments.show', $shipment))->assertOk()->assertSee('Confirm dispatch');

        $this->actingAs($admin)->post(route('admin.shipments.ship', $shipment), [
            'courier_id' => $courier->id, 'tracking_number' => 'CB-TRACK-001', 'service_name' => 'Express',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('shipped', $order->order_status);
        $this->assertSame('J&T Express', $order->courier_name);
        $this->assertSame('CB-TRACK-001', $order->tracking_number);
        $this->assertSame(7, $product->fresh()->stock);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'status_updated');
    }

    public function test_unpaid_or_cancelled_order_cannot_create_a_shipment(): void
    {
        $admin = $this->admin();
        $courier = $this->courier();
        foreach ([['payment_status' => 'pending', 'order_status' => 'packed'], ['payment_status' => 'paid', 'order_status' => 'cancelled']] as $attributes) {
            $order = $this->order($attributes);
            $this->actingAs($admin)->post(route('admin.orders.shipments.store', $order), ['courier_id' => $courier->id, 'tracking_number' => 'NOPE'])->assertSessionHasErrors('shipment');
        }
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_tracking_number_is_required_before_shipment_can_be_marked_shipped(): void
    {
        $admin = $this->admin();
        $courier = $this->courier();
        $shipment = $this->shipment($this->order(['payment_status' => 'paid', 'order_status' => 'packed']), $courier);

        $this->actingAs($admin)->post(route('admin.shipments.ship', $shipment), ['courier_id' => $courier->id])->assertSessionHasErrors('tracking_number');
        $this->assertSame('draft', $shipment->fresh()->shipment_status);
    }

    public function test_secure_tracking_page_exposes_tracking_only_to_the_valid_guest_token(): void
    {
        $order = $this->order(['payment_status' => 'paid', 'order_status' => 'shipped']);
        $shipment = $this->shipment($order, $this->courier(), ['shipment_status' => 'in_transit', 'tracking_number' => 'TRACK-SECURE']);
        $shipment->events()->create(['status' => 'in_transit', 'title' => 'In Transit', 'event_time' => now(), 'source' => 'admin']);

        $this->get(route('shipments.guest.track', ['order' => $order->order_number, 'token' => $order->guest_access_token]))
            ->assertOk()->assertSee('TRACK-SECURE')->assertDontSee($order->address_line_1);
        $this->get(route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]))
            ->assertOk()->assertSee('TRACK-SECURE');
        $this->get(route('shipments.guest.track', ['order' => $order->order_number, 'token' => Str::random(64)]))->assertForbidden();
    }

    public function test_private_label_is_available_only_to_authorised_admins(): void
    {
        Storage::fake('local');
        $label = UploadedFile::fake()->create('label.pdf', 50, 'application/pdf')->store('shipment-labels', 'local');
        $shipment = $this->shipment($this->order(['payment_status' => 'paid', 'order_status' => 'packed']), $this->courier(), ['label_path' => $label]);

        $this->actingAs(User::factory()->create())->get(route('admin.shipments.label.download', $shipment))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.shipments.label.download', $shipment))->assertOk();
    }

    public function test_delivery_event_updates_order_and_duplicate_delivery_event_does_not_resend_email(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $courier = $this->courier();
        $order = $this->order(['payment_status' => 'paid', 'order_status' => 'shipped']);
        $shipment = $this->shipment($order, $courier, ['shipment_status' => 'shipped', 'tracking_number' => 'TRACK-DELIVERY', 'shipped_at' => now()->subDay()]);

        $payload = ['status' => 'delivered', 'title' => 'Delivered', 'event_time' => now()->format('Y-m-d H:i:s')];
        $this->actingAs($admin)->post(route('admin.shipments.events.store', $shipment), $payload)->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->order_status);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'status_updated');

        Notification::fake();
        $this->actingAs($admin)->post(route('admin.shipments.events.store', $shipment->fresh()), $payload)->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_tracking_template_is_safely_applied_and_shipment_reports_count_delivery_states(): void
    {
        Cache::flush();
        $courier = $this->courier(['tracking_url_template' => 'https://tracking.example.test/{tracking_number}']);
        $this->assertSame('https://tracking.example.test/AB%20123', $courier->trackingUrl('AB 123'));
        $this->shipment($this->order(), $courier, ['shipment_status' => 'delivered', 'shipped_at' => now()->subDays(2), 'delivered_at' => now()]);
        $this->shipment($this->order(), $courier, ['shipment_status' => 'delivery_failed']);

        $report = app(AdminReportsService::class)->report(['range' => 'today', 'from_date' => null, 'to_date' => null]);
        $this->assertSame(1, $report['shipments']['delivered']);
        $this->assertSame(1, $report['shipments']['delivery_failed']);
    }

    private function admin(): User { return User::factory()->create(['is_admin' => true]); }
    private function product(array $attributes = []): Product { return Product::create(array_merge(['name' => 'Shipment Piece '.Str::random(6), 'description' => 'Test item', 'price' => '50.00', 'status' => 'active', 'stock' => 10], $attributes)); }
    private function courier(array $attributes = []): Courier { return Courier::create(array_merge(['name' => 'J&T Express', 'code' => 'COURIER_'.Str::upper(Str::random(6)), 'is_active' => true, 'sort_order' => 0], $attributes)); }
    private function order(array $attributes = []): Order { $number = 'CB-SHIP-'.Str::upper(Str::random(8)); return Order::create(array_merge(['number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64), 'customer_name' => 'Guest Customer', 'customer_email' => Str::lower(Str::random(8)).'@example.test', 'customer_phone' => '0123456789', 'address_line_1' => '1 Private Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia', 'shipping_address' => ['address_line_1' => '1 Private Street'], 'shipping_method_name' => 'Standard Delivery', 'subtotal' => '100.00', 'shipping_fee' => '0.00', 'total' => '100.00', 'payment_method' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'packed', 'status' => 'pending'], $attributes)); }
    private function shipment(Order $order, Courier $courier, array $attributes = []): Shipment { return Shipment::create(array_merge(['shipment_number' => 'SHP-CB-'.Str::upper(Str::random(8)), 'order_id' => $order->id, 'courier_id' => $courier->id, 'courier_name_snapshot' => $courier->name, 'tracking_url' => $courier->trackingUrl($attributes['tracking_number'] ?? 'TRACK-1'), 'shipment_status' => 'draft', 'shipment_type' => 'outbound'], $attributes)); }
}
