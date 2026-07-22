<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Notifications\OrderCustomerNotification;
use App\Notifications\AdminOperationalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_management_requires_an_admin_and_accepts_a_product(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.products.index'))->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pending Orders')
            ->assertSee('Pending DuitNow Receipts')
            ->assertSee('Low Stock Products');
        $this->actingAs($admin)->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Customer records');
        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Gross paid revenue');
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Atelier Jacket', 'description' => 'A tailored piece.', 'price' => 420, 'stock' => 8, 'status' => 'active',
            'image' => UploadedFile::fake()->image('atelier-jacket.jpg'),
        ])->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', ['name' => 'Atelier Jacket', 'stock' => 8, 'status' => 'active']);
        Storage::disk('public')->assertExists(Product::query()->where('name', 'Atelier Jacket')->value('image_path'));
    }

    public function test_guest_cart_enforces_current_stock(): void
    {
        $product = $this->product(['stock' => 3]);

        $this->post(route('cart.store', $product), ['quantity' => 2])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart.'.$product->id, 2);

        $this->patch(route('cart.update', $product), ['quantity' => 4])
            ->assertRedirect()
            ->assertSessionHasErrors('quantity');
    }

    public function test_shipping_quotes_cover_standard_express_pickup_and_an_unavailable_area(): void
    {
        $zone = $this->shippingZone(['state' => 'Selangor', 'city_or_area' => 'Ampang', 'base_fee' => '8.00']);
        $standard = $this->deliveryMethod(['code' => 'standard', 'name' => 'Standard Delivery', 'additional_fee' => '0.00']);
        $express = $this->deliveryMethod(['code' => 'express', 'name' => 'Express Delivery', 'additional_fee' => '12.00']);
        $pickup = $this->deliveryMethod(['code' => 'pickup', 'name' => 'Self Pickup', 'is_pickup' => true]);

        $payload = ['state' => 'Selangor', 'city' => 'Ampang', 'postcode' => '68000'];

        $this->postJson(route('shipping.quote'), $payload + ['delivery_method_id' => $standard->id])
            ->assertOk()->assertJsonPath('shipping_zone_id', $zone->id)->assertJsonPath('shipping_fee', '8.00');
        $this->postJson(route('shipping.quote'), $payload + ['delivery_method_id' => $express->id])
            ->assertOk()->assertJsonPath('shipping_fee', '20.00');
        $this->postJson(route('shipping.quote'), ['delivery_method_id' => $pickup->id])
            ->assertOk()->assertJsonPath('shipping_fee', '0.00')->assertJsonPath('shipping_zone_id', null);
        $this->postJson(route('shipping.quote'), ['state' => 'Johor', 'city' => 'Johor Bahru', 'postcode' => '80000', 'delivery_method_id' => $standard->id])
            ->assertUnprocessable()->assertJsonValidationErrors('shipping');
    }

    public function test_guest_checkout_recalculates_totals_and_deducts_stock_once(): void
    {
        Notification::fake();
        $product = $this->product(['price' => '100.00', 'stock' => 5]);
        $method = $this->deliveryMethod(['code' => 'standard', 'additional_fee' => '0.00']);
        $this->shippingZone(['state' => 'Selangor', 'city_or_area' => 'Ampang', 'base_fee' => '10.00']);

        $response = $this->withSession(['cart' => [$product->id => 2]])->post(route('checkout.store'), $this->checkoutData($method) + [
            'shipping_fee' => 0,
            'subtotal' => 1,
            'total' => 1,
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.guest.duitnow', ['order' => $order->order_number, 'token' => $order->guest_access_token]));
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('10.00', $order->shipping_fee);
        $this->assertSame('210.00', $order->total);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->order_status);
        $this->assertNotEmpty($order->guest_access_token);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'order_placed');
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'new_order');
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'low_stock');
    }

    public function test_guest_self_pickup_checkout_does_not_require_an_address(): void
    {
        $product = $this->product(['stock' => 2]);
        $pickup = $this->deliveryMethod(['code' => 'pickup', 'name' => 'Self Pickup', 'is_pickup' => true]);

        $this->withSession(['cart' => [$product->id => 1]])->post(route('checkout.store'), [
            'customer_name' => 'Guest Customer', 'customer_email' => 'guest@example.test', 'customer_phone' => '0123456789',
            'delivery_method_id' => $pickup->id, 'payment_method' => 'duitnow',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('0.00', $order->shipping_fee);
        $this->assertNotEmpty($order->pickup_location);
    }

    public function test_guest_order_access_requires_its_secure_token(): void
    {
        $order = $this->order();

        $this->get(route('orders.guest.show', ['order' => $order->number, 'token' => $order->guest_access_token]))->assertOk();
        $this->get(route('orders.guest.show', ['order' => $order->number, 'token' => Str::random(64)]))->assertForbidden();
    }

    public function test_receipt_upload_rejects_an_invalid_file_type(): void
    {
        $order = $this->order();

        $this->post(route('orders.payment-receipts.store', ['order' => $order->number, 'token' => $order->guest_access_token]), [
            'receipt' => UploadedFile::fake()->create('receipt.txt', 100, 'text/plain'),
        ])->assertSessionHasErrors('receipt');

        $this->assertDatabaseCount('payment_receipts', 0);
    }

    public function test_receipt_upload_rejection_replacement_and_approval_keep_payment_and_stock_consistent(): void
    {
        Storage::fake('local');
        Notification::fake();
        $order = $this->order();
        $product = $this->product(['stock' => 3]);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => '100.00', 'total' => '100.00', 'line_total' => '100.00']);

        $this->post(route('orders.payment-receipts.store', ['order' => $order->number, 'token' => $order->guest_access_token]), ['receipt' => UploadedFile::fake()->image('receipt.jpg')])->assertRedirect();
        $receipt = PaymentReceipt::query()->firstOrFail();
        $this->assertSame('pending', $receipt->status);
        $this->assertSame('local', $receipt->storage_disk);
        Storage::disk('local')->assertExists($receipt->path);
        Storage::disk('public')->assertMissing($receipt->path);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'receipt_submitted');
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'new_duitnow_receipt');

        $this->post(route('orders.payment-receipts.store', ['order' => $order->number, 'token' => $order->guest_access_token]), ['receipt' => UploadedFile::fake()->image('second.jpg')])->assertStatus(422);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->patch(route('admin.payment-receipts.reject', $receipt), ['rejection_reason' => 'Image is unreadable'])->assertRedirect();
        $this->assertSame('rejected', $receipt->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'receipt_rejected');

        $this->post(route('orders.payment-receipts.store', ['order' => $order->number, 'token' => $order->guest_access_token]), ['receipt' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf')])->assertRedirect();
        $replacement = PaymentReceipt::query()->latest('id')->firstOrFail();
        $this->assertSame('pending', $replacement->status);

        $this->actingAs($admin)->patch(route('admin.payment-receipts.approve', $replacement))->assertRedirect();
        $this->assertSame('approved', $replacement->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(3, $product->fresh()->stock);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'payment_approved');
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'duitnow_payment_approved');

        $this->post(route('orders.payment-receipts.store', ['order' => $order->number, 'token' => $order->guest_access_token]), ['receipt' => UploadedFile::fake()->image('paid.jpg')])->assertStatus(422);
    }

    public function test_fulfilment_requires_payment_tracking_and_restores_stock_only_once_on_cancellation(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $product = $this->product(['stock' => 3]);
        $order = $this->order(['payment_status' => 'paid']);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => '100.00', 'total' => '200.00', 'line_total' => '200.00']);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'processing'])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'packed'])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'shipped'])->assertSessionHasErrors(['courier_name', 'tracking_number']);
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'shipped', 'courier_name' => 'DHL', 'tracking_number' => 'TRACK-123'])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['order_status' => 'delivered', 'courier_name' => 'DHL', 'tracking_number' => 'TRACK-123'])->assertRedirect();
        $this->assertNotNull($order->fresh()->delivered_at);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, fn (OrderCustomerNotification $notification) => $notification->event === 'status_updated');

        $unpaid = $this->order();
        $this->actingAs($admin)->patch(route('admin.orders.update', $unpaid), ['order_status' => 'processing'])->assertSessionHasErrors('order_status');

        $cancelled = $this->order(['payment_status' => 'paid']);
        $cancelled->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 2, 'unit_price' => '100.00', 'total' => '200.00', 'line_total' => '200.00']);
        $this->actingAs($admin)->patch(route('admin.orders.update', $cancelled), ['order_status' => 'cancelled', 'cancellation_reason' => 'Customer request'])->assertRedirect();
        $this->assertSame(5, $product->fresh()->stock);
        $this->actingAs($admin)->patch(route('admin.orders.update', $cancelled), ['order_status' => 'cancelled', 'cancellation_reason' => 'Customer request'])->assertRedirect();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertNotNull($cancelled->fresh()->stock_restored_at);
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'order_cancelled');
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge(['name' => 'Silk Dress '.Str::random(6), 'description' => 'A considered piece.', 'price' => '100.00', 'status' => 'active', 'stock' => 10], $attributes));
    }

    private function shippingZone(array $attributes = []): ShippingZone
    {
        return ShippingZone::create(array_merge(['name' => 'Test Zone '.Str::random(6), 'state' => 'Selangor', 'base_fee' => '8.00', 'is_active' => true, 'sort_order' => 0], $attributes));
    }

    private function deliveryMethod(array $attributes = []): DeliveryMethod
    {
        return DeliveryMethod::create(array_merge(['name' => 'Standard Delivery', 'code' => 'method-'.Str::lower(Str::random(8)), 'additional_fee' => '0.00', 'is_pickup' => false, 'is_active' => true, 'sort_order' => 0], $attributes));
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-TEST-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64),
            'customer_name' => 'Guest Customer', 'customer_email' => 'guest@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Test Street'], 'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00', 'shipping_fee' => '0.00', 'total' => '100.00', 'payment_method' => 'duitnow',
            'payment_status' => 'pending', 'order_status' => 'pending', 'status' => 'pending',
        ], $attributes));
    }

    private function checkoutData(DeliveryMethod $method): array
    {
        return [
            'customer_name' => 'Guest Customer', 'customer_email' => 'guest@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'delivery_method_id' => $method->id, 'payment_method' => 'duitnow',
        ];
    }
}
