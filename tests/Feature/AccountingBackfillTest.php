<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingAuditLog;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_orders_are_dry_run_safe_and_backfilled_exactly_once(): void
    {
        $order = $this->paidOrder();

        $this->artisan('accounting:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('would-post')
            ->assertExitCode(0);

        $this->assertDatabaseCount('journal_entries', 0);

        $this->artisan('accounting:backfill')->assertExitCode(0);
        $this->artisan('accounting:backfill')->assertExitCode(0);

        $this->assertSame(1, JournalEntry::query()->where([
            'source_type' => 'order',
            'source_id' => $order->id,
            'source_event' => 'paid',
        ])->count());
        $this->assertSame(1, AccountingAuditLog::query()->where('action', 'accounting.backfill.order_synchronized')->count());
    }

    public function test_completed_refunds_are_backfilled_once_and_pending_events_are_ignored(): void
    {
        $order = $this->paidOrder();
        $refund = Refund::query()->create([
            'refund_number' => 'RFD-'.Str::upper(Str::random(8)),
            'order_id' => $order->id,
            'payment_provider' => 'duitnow',
            'refund_type' => 'partial',
            'status' => 'succeeded',
            'amount' => '25.00',
            'currency' => 'MYR',
            'reason' => 'Returned item',
            'requested_at' => now(),
            'confirmed_at' => now(),
        ]);
        $this->paidOrder(['payment_status' => 'pending']);

        $this->artisan('accounting:backfill')->assertExitCode(0);
        $this->artisan('accounting:backfill')->assertExitCode(0);

        $this->assertSame(1, JournalEntry::query()->where([
            'source_type' => 'refund',
            'source_id' => $refund->id,
            'source_event' => 'completed',
        ])->count());
        $this->assertSame(1, JournalEntry::query()->where('source_type', 'order')->count());
    }

    public function test_dashboard_uses_paid_orders_for_money_received_and_keeps_pending_payments_separate(): void
    {
        $this->paidOrder();
        $this->paidOrder(['payment_status' => 'pending', 'total' => '60.00']);

        $cards = app(AccountingReportService::class)->overview(['range' => 'today'])['cards'];

        $this->assertSame(10000, $cards['money_received']);
        $this->assertSame(6000, $cards['pending_payments']);
        $this->assertSame(1, $cards['paid_orders']);
        $this->assertSame(1, $cards['unpaid_orders']);
    }

    public function test_admin_can_view_and_void_an_unposted_expense_without_creating_a_journal(): void
    {
        app(AccountingAccountService::class)->ensureDefaults();
        $expense = Expense::query()->create([
            'expense_number' => 'EXP-VOID-001',
            'expense_date' => now()->toDateString(),
            'accounting_date' => now()->toDateString(),
            'debit_account_id' => AccountingAccount::query()->where('code', '6030')->value('id'),
            'payment_account_id' => AccountingAccount::query()->where('code', '1010')->value('id'),
            'description' => 'Draft advertising expense',
            'amount' => '15.00',
            'status' => 'draft',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.accounting.expenses.show', $expense))->assertOk();
        $this->actingAs($admin)->post(route('admin.accounting.expenses.void', $expense))->assertRedirect(route('admin.accounting.expenses.index'));

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'status' => 'voided', 'journal_entry_id' => null]);
        $this->assertDatabaseHas('accounting_audit_logs', ['action' => 'expense.voided', 'record_id' => $expense->id]);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->actingAs($admin)->get(route('admin.accounting.expenses.edit', $expense))->assertStatus(409);
    }

    private function paidOrder(array $overrides = []): Order
    {
        app(AccountingAccountService::class)->ensureDefaults();
        $number = 'CB-BACKFILL-'.Str::upper(Str::random(8));

        return Order::query()->create(array_merge([
            'number' => $number,
            'order_number' => $number,
            'guest_access_token' => Str::random(64),
            'customer_name' => 'Backfill Customer',
            'customer_email' => 'backfill@example.test',
            'shipping_address' => ['country' => 'Malaysia'],
            'subtotal' => '100.00',
            'original_shipping_fee' => '0.00',
            'shipping_fee' => '0.00',
            'discount_amount' => '0.00',
            'free_shipping_discount' => '0.00',
            'gift_wrapping' => false,
            'gift_wrapping_fee' => '0.00',
            'total' => '100.00',
            'payment_method' => 'duitnow',
            'payment_provider' => 'duitnow',
            'payment_status' => 'paid',
            'order_status' => 'pending',
            'status' => 'pending',
        ], $overrides));
    }
}
