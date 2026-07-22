<?php

namespace Tests\Feature;

use App\Jobs\QueueProductStockNotifications;
use App\Jobs\SendProductStockNotification;
use App\Mail\BackInStockMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStockNotification;
use App\Models\User;
use App\Services\ProductStockNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackInStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_of_stock_product_shows_the_notification_form_but_in_stock_product_does_not(): void
    {
        $soldOut = $this->product(['stock' => 0]);
        $available = $this->product(['stock' => 2]);

        $this->get(route('products.show', $soldOut))
            ->assertOk()
            ->assertSee('Notify Me When Available')
            ->assertSee('Notify Me');

        $this->get(route('products.show', $available))
            ->assertOk()
            ->assertDontSee('Notify Me When Available')
            ->assertSee('Add to bag');
    }

    public function test_guest_can_request_a_back_in_stock_notification_without_marketing_subscription(): void
    {
        $product = $this->product(['stock' => 0]);

        $this->from(route('products.show', $product))
            ->post(route('product-stock-notifications.store', $product), ['email' => '  Guest@Example.test ', 'name' => 'Cherry Guest'])
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('stock_notification_success', 'We’ll notify you when this item is back in stock.');

        $this->assertDatabaseHas('product_stock_notifications', [
            'product_id' => $product->id,
            'email' => 'guest@example.test',
            'waiting_email' => 'guest@example.test',
            'name' => 'Cherry Guest',
            'status' => 'waiting',
        ]);
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_duplicate_waiting_requests_are_prevented_and_invalid_email_is_rejected(): void
    {
        $product = $this->product(['stock' => 0]);
        $payload = ['email' => 'guest@example.test'];

        $this->post(route('product-stock-notifications.store', $product), $payload)
            ->assertSessionHas('stock_notification_success');
        $this->post(route('product-stock-notifications.store', $product), ['email' => 'GUEST@EXAMPLE.TEST'])
            ->assertSessionHas('stock_notification_success', 'You are already waiting for this item.');
        $this->assertDatabaseCount('product_stock_notifications', 1);

        $this->from(route('products.show', $product))
            ->post(route('product-stock-notifications.store', $product), ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['email']);
    }

    public function test_zero_to_positive_admin_stock_update_dispatches_a_queued_notification_batch(): void
    {
        Queue::fake();
        $product = $this->product(['stock' => 0]);
        $this->waiting($product);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->productPayload($product, ['stock' => 4]))
            ->assertRedirect(route('admin.products.index'));

        Queue::assertPushed(QueueProductStockNotifications::class, fn (QueueProductStockNotifications $job) => $job->productId === $product->id);
    }

    public function test_zero_to_zero_and_positive_to_positive_stock_changes_do_not_dispatch(): void
    {
        Queue::fake();
        $zero = $this->product(['stock' => 0]);
        $positive = $this->product(['stock' => 5]);
        $this->waiting($zero);
        $this->waiting($positive);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.products.update', $zero), $this->productPayload($zero, ['stock' => 0]));
        $this->actingAs($admin)->put(route('admin.products.update', $positive), $this->productPayload($positive, ['stock' => 3]));

        Queue::assertNotPushed(QueueProductStockNotifications::class);
    }

    public function test_inactive_product_restock_does_not_dispatch(): void
    {
        Queue::fake();
        $product = $this->product(['stock' => 0, 'status' => 'archived']);
        $this->waiting($product);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->productPayload($product, ['stock' => 5, 'status' => 'archived']));

        Queue::assertNotPushed(QueueProductStockNotifications::class);
    }

    public function test_cancelling_an_order_queues_notifications_when_stock_is_restored_from_zero(): void
    {
        Queue::fake();
        $product = $this->product(['stock' => 0]);
        $this->waiting($product);
        $order = $this->orderFor($product);

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update', $order), [
                'order_status' => 'cancelled',
                'cancellation_reason' => 'Customer request before packing.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, $product->fresh()->stock);
        Queue::assertPushed(QueueProductStockNotifications::class, fn (QueueProductStockNotifications $job) => $job->productId === $product->id);
    }

    public function test_secure_cancellation_token_works_and_invalid_token_fails_safely(): void
    {
        $notification = $this->waiting($this->product(['stock' => 0]));

        $this->get(route('product-stock-notifications.cancel', $notification->notification_token))
            ->assertOk()
            ->assertSee('You will no longer receive a back-in-stock notification for this item.');
        $this->assertDatabaseHas('product_stock_notifications', ['id' => $notification->id, 'status' => 'cancelled']);

        $this->get(route('product-stock-notifications.cancel', Str::random(64)))->assertNotFound();
    }

    public function test_back_in_stock_delivery_sends_once_and_marks_the_request_notified(): void
    {
        Mail::fake();
        $product = $this->product(['stock' => 1]);
        $notification = $this->waiting($product);
        $service = app(ProductStockNotificationService::class);

        (new SendProductStockNotification($notification->id))->handle($service);
        (new SendProductStockNotification($notification->id))->handle($service);

        $this->assertDatabaseHas('product_stock_notifications', [
            'id' => $notification->id,
            'status' => 'notified',
        ]);
        Mail::assertSent(BackInStockMail::class, function (BackInStockMail $mail) use ($notification): bool {
            $this->assertStringContainsString($notification->notification_token, $mail->render());

            return true;
        });
        Mail::assertSent(BackInStockMail::class, 1);
    }

    public function test_restock_job_skips_a_product_that_becomes_unavailable_before_send(): void
    {
        Mail::fake();
        Queue::fake();
        $product = $this->product(['stock' => 0]);
        $notification = $this->waiting($product);
        $product->update(['stock' => 2]);
        app(ProductStockNotificationService::class)->handleStockChange($product, 0);
        Product::query()->whereKey($product->id)->update(['stock' => 0]);
        $this->assertSame(0, $product->fresh()->stock);

        (new SendProductStockNotification($notification->id))->handle(app(ProductStockNotificationService::class));

        $this->assertDatabaseHas('product_stock_notifications', ['id' => $notification->id, 'status' => 'waiting']);
        Mail::assertNothingSent();
    }

    public function test_non_admin_cannot_access_back_in_stock_requests(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.product-stock-notifications.index'))
            ->assertForbidden();
    }

    private function product(array $attributes = []): Product
    {
        $name = $attributes['name'] ?? 'Restock Piece '.Str::random(7);

        return Product::create(array_merge([
            'name' => $name,
            'description' => 'A considered Cherry Bellemont piece.',
            'price' => '250.00',
            'stock' => 0,
            'status' => 'active',
            'featured' => false,
        ], $attributes));
    }

    private function waiting(Product $product, array $attributes = []): ProductStockNotification
    {
        $email = $attributes['email'] ?? 'guest-'.Str::lower(Str::random(8)).'@example.test';

        return ProductStockNotification::create(array_merge([
            'product_id' => $product->id,
            'email' => $email,
            'waiting_email' => $email,
            'name' => 'Cherry Guest',
            'status' => 'waiting',
            'notification_token' => Str::random(64),
            'requested_at' => now(),
        ], $attributes));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function productPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'status' => $product->status,
        ], $overrides);
    }

    private function orderFor(Product $product): Order
    {
        $number = 'CB-RESTOCK-'.Str::upper(Str::random(8));
        $order = Order::create([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Stock Customer',
            'customer_email' => 'stock-customer@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Stock Street',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Stock Street'],
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '250.00',
            'shipping_fee' => '0.00',
            'total' => '250.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'status' => 'processing',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '250.00',
            'total' => '250.00',
            'line_total' => '250.00',
        ]);

        return $order;
    }
}
