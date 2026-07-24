<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\RefundCalculator;
use App\Notifications\ReturnCustomerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReturnsAndRefundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_an_eligible_return_and_only_access_it_with_the_secure_token(): void
    {
        Notification::fake();
        $order = $this->eligibleOrder();
        $item = $order->items()->first();

        $this->post(route('returns.guest.store', ['order' => $order->order_number, 'token' => $order->guest_access_token]), $this->requestData($item))
            ->assertRedirect();

        $return = ReturnRequest::query()->firstOrFail();
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'order_id' => $order->id, 'status' => 'requested']);
        $this->assertDatabaseHas('return_request_items', ['return_request_id' => $return->id, 'order_item_id' => $item->id, 'requested_quantity' => 1]);

        $this->get(route('returns.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token, 'returnRequest' => $return]))
            ->assertOk()->assertSee($return->return_number);
        $this->get(route('returns.guest.show', ['order' => $order->order_number, 'token' => Str::random(64), 'returnRequest' => $return]))->assertForbidden();
    }

    public function test_unpaid_or_undelivered_orders_cannot_open_a_return_request(): void
    {
        $order = $this->eligibleOrder(['payment_status' => 'pending', 'order_status' => 'pending', 'delivered_at' => null]);

        $this->get(route('returns.guest.create', ['order' => $order->order_number, 'token' => $order->guest_access_token]))->assertForbidden();
    }

    public function test_non_admin_cannot_access_return_administration(): void
    {
        $return = $this->returnRequest();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.returns.show', $return))
            ->assertForbidden();
    }

    public function test_inspection_restock_happens_once_and_damaged_items_are_not_restocked(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $return = $this->returnRequest(['status' => 'inspecting']);
        $returnItem = $return->items()->first();
        $product = $returnItem->product;
        $product->update(['stock' => 2]);

        $payload = [
            'passed' => 1,
            'reason' => 'Item passed inspection.',
            'items' => [$returnItem->id => ['condition_received' => 'Unused', 'inspection_notes' => 'Approved.', 'stock_disposition' => 'restocked']],
        ];
        $this->actingAs($admin)->patch(route('admin.returns.finish-inspection', $return), $payload)->assertRedirect();
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertNotNull($returnItem->fresh()->restocked_at);

        $this->actingAs($admin)->patch(route('admin.returns.finish-inspection', $return->fresh()), $payload);
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public function test_manual_refund_requires_a_reference_and_keeps_payment_status_separate(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);
        $return = $this->returnRequest(['status' => 'resolution_pending']);
        $refund = Refund::create([
            'refund_number' => 'RFD-CB-TEST-'.Str::upper(Str::random(6)),
            'return_request_id' => $return->id,
            'order_id' => $return->order_id,
            'payment_provider' => 'duitnow',
            'refund_type' => 'partial',
            'status' => 'pending',
            'amount' => '10.00',
            'reason' => 'Approved item return.',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.refunds.manual-confirm', $refund), [])->assertSessionHasErrors('manual_reference');
        $this->actingAs($admin)->patch(route('admin.refunds.manual-confirm', $refund), ['manual_reference' => 'DNT-REF-001', 'manual_proof' => UploadedFile::fake()->create('refund-proof.pdf', 20, 'application/pdf')])->assertRedirect();

        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'succeeded', 'manual_reference' => 'DNT-REF-001']);
        $this->assertSame('paid', $return->order->fresh()->payment_status);
        $this->assertSame('partially_refunded', $return->order->fresh()->refund_status);
    }

    public function test_refund_calculator_uses_snapshots_allocates_coupon_discount_and_does_not_refund_gift_wrapping_automatically(): void
    {
        $order = $this->eligibleOrder(['subtotal' => '200.00', 'total' => '210.00', 'discount_amount' => '20.00', 'shipping_fee' => '0.00', 'gift_wrapping' => true, 'gift_wrapping_fee' => '30.00']);
        $secondProduct = Product::create(['name' => 'Second Item', 'description' => 'Details', 'price' => '100.00', 'stock' => 3, 'status' => 'active']);
        $secondItem = OrderItem::create(['order_id' => $order->id, 'product_id' => $secondProduct->id, 'name' => $secondProduct->name, 'product_name' => $secondProduct->name, 'quantity' => 1, 'unit_price' => '100.00', 'total' => '100.00', 'line_total' => '100.00']);
        $return = $this->returnRequest(['order_id' => $order->id, 'status' => 'resolution_pending']);
        $return->items()->delete();
        $return->items()->create(['order_item_id' => $order->items()->first()->id, 'product_id' => $order->items()->first()->product_id, 'product_name' => 'Return Item', 'requested_quantity' => 1, 'approved_quantity' => 1, 'unit_price' => '100.00', 'line_paid_amount' => '100.00', 'reason' => 'defective_item', 'stock_disposition' => 'pending']);

        $calculation = app(RefundCalculator::class)->calculate($return->fresh(['items', 'order.items', 'order.refunds']));

        $this->assertSame(9000, $calculation['item_amount']);
        $this->assertSame(0, $calculation['gift_wrap_amount']);
        $this->assertSame(9000, $calculation['total_amount']);
        $this->assertNotNull($secondItem->id);
    }

    public function test_succeeded_refund_credit_note_is_available_only_to_the_secure_guest(): void
    {
        $return = $this->returnRequest(['status' => 'completed']);
        $refund = Refund::create(['refund_number' => 'RFD-CB-TEST-'.Str::upper(Str::random(6)), 'return_request_id' => $return->id, 'order_id' => $return->order_id, 'payment_provider' => 'duitnow', 'refund_type' => 'partial', 'status' => 'succeeded', 'amount' => '10.00', 'reason' => 'Approved.', 'requested_at' => now(), 'confirmed_at' => now()]);
        $order = $return->order;

        $this->get(route('returns.guest.credit-note', ['order' => $order->order_number, 'token' => $order->guest_access_token, 'returnRequest' => $return, 'refund' => $refund]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('returns.guest.credit-note', ['order' => $order->order_number, 'token' => Str::random(64), 'returnRequest' => $return, 'refund' => $refund]))->assertForbidden();
    }

    public function test_verified_stripe_refund_webhook_confirms_the_refund_without_changing_the_original_payment_status(): void
    {
        Notification::fake();
        $return = $this->returnRequest(['status' => 'resolution_pending']);
        $order = $return->order;
        $order->update(['payment_method' => 'stripe', 'payment_provider' => 'stripe', 'payment_status' => 'paid', 'stripe_payment_intent_id' => 'pi_return_refund']);
        $refund = Refund::create(['refund_number' => 'RFD-CB-TEST-'.Str::upper(Str::random(6)), 'return_request_id' => $return->id, 'order_id' => $order->id, 'payment_provider' => 'stripe', 'refund_type' => 'partial', 'status' => 'processing', 'amount' => '10.00', 'reason' => 'Approved.', 'stripe_refund_id' => 're_return_refund', 'stripe_payment_intent_id' => 'pi_return_refund', 'requested_at' => now(), 'processed_at' => now()]);
        $event = ['id' => 'evt_return_refund', 'type' => 'refund.updated', 'data' => ['object' => ['id' => 're_return_refund', 'payment_intent' => 'pi_return_refund', 'amount' => 1000, 'currency' => 'myr', 'status' => 'succeeded']]];
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('stripe.webhook_secret'));

        $this->call('POST', route('stripe.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature], $payload)->assertOk();

        $this->assertSame('succeeded', $refund->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Notification::assertSentOnDemand(ReturnCustomerNotification::class);
    }

    private function requestData(OrderItem $item): array
    {
        return ['request_type' => 'return', 'preferred_resolution' => 'refund', 'customer_details' => 'The item arrived damaged.', 'policy_acknowledged' => 1, 'items' => [$item->id => ['order_item_id' => $item->id, 'quantity' => 1, 'reason' => 'damaged_item']]];
    }

    private function eligibleOrder(array $attributes = []): Order
    {
        $number = 'CB-RET-'.Str::upper(Str::random(8));
        $order = Order::create(array_merge(['number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64), 'customer_name' => 'Guest Customer', 'customer_email' => 'guest@example.test', 'customer_phone' => '0123456789', 'address_line_1' => '1 Test Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia', 'shipping_address' => ['address_line_1' => '1 Test Street'], 'shipping_method_name' => 'Standard Delivery', 'subtotal' => '100.00', 'shipping_fee' => '0.00', 'total' => '100.00', 'payment_method' => 'duitnow', 'payment_provider' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'delivered', 'status' => 'delivered', 'delivered_at' => now()->subDay()], $attributes));
        $product = Product::create(['name' => 'Return Item', 'description' => 'Details', 'price' => '100.00', 'stock' => 5, 'status' => 'active']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => '100.00', 'total' => '100.00', 'line_total' => '100.00']);

        return $order->fresh('items.product');
    }

    private function returnRequest(array $attributes = []): ReturnRequest
    {
        $order = $attributes['order_id'] ?? null ? Order::findOrFail($attributes['order_id']) : $this->eligibleOrder();
        $item = $order->items()->first();
        $return = ReturnRequest::create(array_merge(['return_number' => 'RET-CB-'.Str::upper(Str::random(8)), 'order_id' => $order->id, 'customer_name' => $order->customer_name, 'customer_email' => $order->customer_email, 'request_type' => 'return', 'status' => 'requested', 'customer_reason' => 'damaged_item', 'requested_at' => now()], $attributes));
        $return->items()->create(['order_item_id' => $item->id, 'product_id' => $item->product_id, 'product_name' => $item->product_name, 'requested_quantity' => 1, 'approved_quantity' => 1, 'unit_price' => $item->unit_price, 'line_paid_amount' => $item->line_total, 'reason' => 'damaged_item', 'stock_disposition' => 'pending']);

        return $return->fresh(['order', 'items.product']);
    }
}
