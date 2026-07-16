<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_invoice_downloads_as_a_pdf_and_gets_a_stable_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order(['payment_status' => 'paid']);

        $response = $this->actingAs($admin)->get(route('admin.orders.invoice', $order));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $invoiceNumber = $order->fresh()->invoice_number;
        $this->assertMatchesRegularExpression('/^INV-CB-\d{8}-\d{4,}$/', (string) $invoiceNumber);

        $this->actingAs($admin)->get(route('admin.orders.invoice', $order->fresh()))->assertOk();
        $this->assertSame($invoiceNumber, $order->fresh()->invoice_number);
    }

    public function test_pending_order_cannot_download_a_final_invoice(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order(['payment_status' => 'pending']);

        $this->actingAs($admin)->get(route('admin.orders.invoice', $order))->assertForbidden();
        $this->get(route('orders.guest.invoice', ['order' => $order->order_number, 'token' => $order->guest_access_token]))->assertForbidden();
    }

    public function test_admin_can_open_a_packing_slip_but_a_non_admin_cannot_access_admin_documents(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);
        $order = $this->order();

        $response = $this->actingAs($admin)->get(route('admin.orders.packing-slip', $order));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->actingAs($customer)->get(route('admin.orders.packing-slip', $order))->assertForbidden();
    }

    public function test_guest_can_download_only_their_own_paid_invoice_with_a_valid_token(): void
    {
        $order = $this->order(['payment_status' => 'paid']);

        $this->get(route('orders.guest.invoice', ['order' => $order->order_number, 'token' => $order->guest_access_token]))->assertOk();
        $this->get(route('orders.guest.invoice', ['order' => $order->order_number, 'token' => Str::random(64)]))->assertForbidden();
    }

    public function test_invoice_uses_stored_totals_coupon_discount_and_shipping_values(): void
    {
        $order = $this->order([
            'payment_status' => 'paid',
            'subtotal' => '350.00',
            'coupon_code' => 'WELCOME10',
            'discount_amount' => '35.00',
            'shipping_fee' => '8.00',
            'free_shipping_discount' => '0.00',
            'total' => '323.00',
        ]);
        $order->items()->create([
            'name' => 'Invoice Snapshot Piece',
            'product_name' => 'Invoice Snapshot Piece',
            'quantity' => 1,
            'unit_price' => '350.00',
            'line_total' => '350.00',
            'total' => '350.00',
        ]);

        $data = app(OrderDocumentService::class)->documentData($order->fresh());
        $html = view('pdf.invoice', $data)->render();

        $this->assertSame(323.0, (float) $data['order']->total);
        $this->assertStringContainsString('WELCOME10', $html);
        $this->assertStringContainsString('RM 35.00', $html);
        $this->assertStringContainsString('RM 8.00', $html);
        $this->assertStringContainsString('RM 323.00', $html);
    }

    private function order(array $attributes = []): Order
    {
        $number = 'CB-PDF-'.Str::upper(Str::random(8));

        return Order::create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'PDF Customer',
            'customer_email' => 'pdf-customer@example.test',
            'customer_phone' => '0123456789',
            'address_line_1' => '1 Invoice Street',
            'city' => 'Kuala Lumpur',
            'state' => 'Kuala Lumpur',
            'postcode' => '50000',
            'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Invoice Street'],
            'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00',
            'shipping_fee' => '8.00',
            'total' => '108.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $attributes));
    }
}
