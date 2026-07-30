<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderNotificationLog;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminMailTestNotification;
use App\Notifications\OrderCustomerNotification;
use App\Services\OrderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestOrderLookupAndMailpitTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_lookup_and_matching_details_redirect_to_the_existing_secure_order_url(): void
    {
        $order = $this->order(['customer_email' => 'Guest.Customer@Example.test']);

        $this->get(route('orders.lookup.form'))->assertOk()->assertSee('Track Your Order');
        $this->post(route('orders.lookup.search'), ['order_number' => ' '.$order->order_number.' ', 'email' => ' GUEST.CUSTOMER@example.test '])
            ->assertRedirect(route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]));
    }

    public function test_invalid_lookup_never_reveals_which_value_did_not_match_or_the_order_details(): void
    {
        $order = $this->order(['customer_email' => 'guest@example.test', 'admin_notes' => 'Private internal note']);

        $this->from(route('orders.lookup.form'))
            ->post(route('orders.lookup.search'), ['order_number' => $order->order_number, 'email' => 'other@example.test'])
            ->assertRedirect(route('orders.lookup.form'))
            ->assertSessionHasErrors('lookup');

        $this->get(route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]))
            ->assertOk()
            ->assertSee('Payment Status')
            ->assertDontSee('Private internal note');
    }

    public function test_lookup_is_rate_limited_and_numeric_id_cannot_access_a_guest_order(): void
    {
        $order = $this->order();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('orders.lookup.search'), ['order_number' => 'UNKNOWN-'.$attempt, 'email' => 'guest@example.test']);
        }
        $this->post(route('orders.lookup.search'), ['order_number' => 'UNKNOWN-LAST', 'email' => 'guest@example.test'])->assertStatus(429);
        $this->get('/orders/'.$order->id)->assertRedirect(route('login'));
    }

    public function test_transactional_mail_uses_a_single_automatic_delivery_log_and_a_manual_resend_is_kept_separate(): void
    {
        Notification::fake();
        $order = $this->order();

        $this->assertTrue(app(OrderNotifier::class)->send($order, 'order_placed'));
        $this->assertFalse(app(OrderNotifier::class)->send($order, 'order_placed'));
        $this->assertTrue(app(OrderNotifier::class)->send($order, 'order_placed', [], true, $this->admin()->id));

        $this->assertDatabaseCount('order_notification_logs', 2);
        $this->assertDatabaseHas('order_notification_logs', ['order_id' => $order->id, 'notification_type' => 'order_placed', 'is_manual_resend' => false]);
        $this->assertDatabaseHas('order_notification_logs', ['order_id' => $order->id, 'notification_type' => 'order_placed', 'is_manual_resend' => true]);
        Notification::assertSentOnDemand(OrderCustomerNotification::class, 2);
    }

    public function test_admin_can_send_a_mailpit_test_email_and_customers_cannot_access_email_logs(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.email-logs.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings.email'))->assertOk()->assertSee('Send Test Email');
        $this->actingAs($admin)->post(route('admin.settings.email.test'), [
            'recipient' => 'mailpit@example.test',
            'subject' => 'Mailpit Test',
            'message' => 'A local delivery test.',
        ])->assertRedirect();

        Notification::assertSentOnDemand(AdminMailTestNotification::class);
        $log = OrderNotificationLog::query()->where('notification_type', 'mail_test')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.email-logs.show', $log))->assertOk()->assertSee('mailpit@example.test');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-LOOKUP-'.Str::upper(Str::random(8));
        $order = Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Guest Customer',
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
            'discount_amount' => '0.00',
            'shipping_fee' => '0.00',
            'total' => '100.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $attributes));
        $product = Product::create(['name' => 'Lookup Piece '.Str::random(6), 'description' => 'Test piece', 'price' => '100.00', 'stock' => 5, 'status' => 'active']);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'size_name' => 'M', 'colour_name' => 'Burgundy', 'quantity' => 1, 'unit_price' => '100.00', 'total' => '100.00', 'line_total' => '100.00']);

        return $order;
    }
}
