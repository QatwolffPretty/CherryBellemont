<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Notifications\AdminOperationalNotification;
use App\Services\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_recipient_uses_configuration_before_the_from_address_fallback(): void
    {
        Notification::fake();
        config(['store.admin_notification_email' => 'operations@example.test', 'mail.from.address' => 'fallback@example.test']);
        $order = $this->order();

        $this->assertTrue(app(AdminNotificationService::class)->send('new_order', ['order' => $order]));

        Notification::assertSentOnDemand(AdminOperationalNotification::class, function (AdminOperationalNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notification->event === 'new_order'
                && $notifiable->routes['mail'] === 'operations@example.test';
        });
    }

    public function test_admin_recipient_falls_back_to_the_from_address_when_configuration_is_empty(): void
    {
        Notification::fake();
        config(['store.admin_notification_email' => null, 'mail.from.address' => 'fallback@example.test']);

        app(AdminNotificationService::class)->send('new_order', ['order' => $this->order()]);

        Notification::assertSentOnDemand(AdminOperationalNotification::class, function (AdminOperationalNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notification->event === 'new_order'
                && $notifiable->routes['mail'] === 'fallback@example.test';
        });
    }

    public function test_low_stock_alert_is_sent_once_when_stock_crosses_the_threshold(): void
    {
        Notification::fake();
        config(['store.low_stock_threshold' => 3]);
        $product = $this->product(['stock' => 5]);
        $method = $this->deliveryMethod();
        $this->shippingZone();

        $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), $this->checkoutData($method))
            ->assertRedirect();
        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->checkoutData($method))
            ->assertRedirect();

        $notifications = Notification::sent(new AnonymousNotifiable(), AdminOperationalNotification::class);
        $this->assertSame(1, $notifications->where('event', 'low_stock')->count());
        $this->assertSame(0, $notifications->where('event', 'out_of_stock')->count());
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_out_of_stock_alert_is_sent_once_when_stock_reaches_zero(): void
    {
        Notification::fake();
        config(['store.low_stock_threshold' => 3]);
        $product = $this->product(['stock' => 1]);
        $method = $this->deliveryMethod();
        $this->shippingZone();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->checkoutData($method))
            ->assertRedirect();

        $notifications = Notification::sent(new AnonymousNotifiable(), AdminOperationalNotification::class);
        $this->assertSame(1, $notifications->where('event', 'out_of_stock')->count());
        $this->assertSame(0, $notifications->where('event', 'low_stock')->count());
        $this->assertSame(0, $product->fresh()->stock);
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-ADMIN-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Admin Email Guest',
            'customer_email' => 'guest@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street',
            'city' => 'Ampang',
            'state' => 'Selangor',
            'postcode' => '68000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Test Street'],
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00',
            'shipping_fee' => '10.00',
            'total' => '110.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $attributes));
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Admin Stock Piece '.Str::random(6),
            'description' => 'A considered piece.',
            'price' => '100.00',
            'stock' => 10,
            'status' => 'active',
        ], $attributes));
    }

    private function shippingZone(): ShippingZone
    {
        return ShippingZone::create([
            'name' => 'Admin Test Zone '.Str::random(6),
            'state' => 'Selangor',
            'city_or_area' => 'Ampang',
            'base_fee' => '10.00',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function deliveryMethod(): DeliveryMethod
    {
        return DeliveryMethod::create([
            'name' => 'Standard Delivery',
            'code' => 'admin-test-'.Str::lower(Str::random(8)),
            'additional_fee' => '0.00',
            'is_pickup' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function checkoutData(DeliveryMethod $method): array
    {
        return [
            'customer_name' => 'Admin Email Guest',
            'customer_email' => 'guest@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Test Street',
            'city' => 'Ampang',
            'state' => 'Selangor',
            'postcode' => '68000',
            'country' => 'Malaysia',
            'delivery_method_id' => $method->id,
            'payment_method' => 'duitnow',
        ];
    }
}
