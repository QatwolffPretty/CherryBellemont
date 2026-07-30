<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OwnerTransaction;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\AccountingPostingService;
use App\Services\AccountingReportService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_creates_balanced_idempotent_journals_with_historical_cost(): void
    {
        $order = $this->paidOrder();
        $posting = app(AccountingPostingService::class);
        $posting->postPaidOrder($order);
        $posting->postPaidOrder($order->fresh('items'));

        $this->assertSame(2, JournalEntry::query()->count());
        $sales = JournalEntry::query()->where('source_type', 'order')->where('source_event', 'paid')->firstOrFail();
        $this->assertSame('posted', $sales->status);
        $this->assertSame($sales->total_debit, $sales->total_credit);
        $this->assertSame('140.00', $sales->total_debit);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $sales->id, 'account_id' => AccountingAccount::where('code', '4090')->value('id'), 'debit' => '10.00']);
        $cogs = JournalEntry::query()->where('source_event', 'cost_of_goods_sold')->firstOrFail();
        $this->assertSame('40.00', $cogs->total_debit);
    }

    public function test_unpaid_orders_never_create_revenue_entries(): void
    {
        $order = $this->paidOrder(['payment_status' => 'pending']);
        $this->assertNull(app(AccountingPostingService::class)->postPaidOrder($order));
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_draft_journals_do_not_appear_in_the_ledger_and_reversals_are_balanced(): void
    {
        app(AccountingAccountService::class)->ensureDefaults();
        $bank = AccountingAccount::where('code', '1010')->firstOrFail();
        $income = AccountingAccount::where('code', '4030')->firstOrFail();
        $posting = app(AccountingPostingService::class);
        $draft = $posting->createDraft(['transaction_date' => now()->toDateString(), 'description' => 'Manual income'], [['account_id' => $bank->id, 'debit' => '25.00', 'credit' => '0.00'], ['account_id' => $income->id, 'debit' => '0.00', 'credit' => '25.00']]);
        $this->assertSame(0, app(AccountingReportService::class)->ledger(['range' => 'today'])['rows']->count());
        $posting->postDraft($draft);
        $this->assertDatabaseCount('journal_entry_lines', 2);
        $this->assertDatabaseHas('journal_entries', ['id' => $draft->id, 'status' => 'posted']);
        $this->assertSame(2, \App\Models\JournalEntryLine::query()->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))->count());
        $this->assertSame(2, app(AccountingReportService::class)->ledger(['range' => 'today'])['rows']->count());
        $reversal = $posting->reverse($draft->fresh());
        $this->assertSame('25.00', $reversal->total_debit);
        $this->assertSame('25.00', $reversal->total_credit);
        $this->assertSame('reversed', $draft->fresh()->status);
    }

    public function test_completed_refunds_post_once_and_sales_summary_excludes_unpaid_orders(): void
    {
        $order = $this->paidOrder();
        app(AccountingPostingService::class)->postPaidOrder($order);
        $refund = Refund::create(['refund_number' => 'RFD-'.Str::upper(Str::random(8)), 'order_id' => $order->id, 'payment_provider' => 'duitnow', 'refund_type' => 'partial', 'status' => 'pending', 'amount' => '20.00', 'currency' => 'MYR', 'reason' => 'Approved return', 'requested_at' => now()]);
        app(RefundService::class)->confirm($refund);
        $this->assertDatabaseHas('journal_entries', ['source_type' => 'refund', 'source_id' => $refund->id, 'source_event' => 'completed', 'status' => 'posted']);
        $this->paidOrder(['payment_status' => 'pending', 'total' => '999.00']);
        $summary = app(AccountingReportService::class)->salesSummary(['range' => 'today']);
        $this->assertSame(1, $summary['paid_order_count']);
        $this->assertSame(13000, $summary['gross_sales']);
        $this->assertSame(2000, $summary['refunds']);
        $this->assertSame(11000, $summary['net_sales']);
    }

    public function test_owner_drawings_do_not_reduce_operating_profit_and_non_admin_is_forbidden(): void
    {
        $order = $this->paidOrder();
        app(AccountingPostingService::class)->postPaidOrder($order);
        app(AccountingAccountService::class)->ensureDefaults();
        $transaction = OwnerTransaction::create(['transaction_number' => 'OWN-TEST-001', 'transaction_date' => now(), 'transaction_type' => 'owner_drawing', 'amount' => '30.00', 'payment_account_id' => AccountingAccount::where('code', '1010')->value('id'), 'destination_account_id' => AccountingAccount::where('code', '3100')->value('id'), 'description' => 'Owner drawing', 'status' => 'draft']);
        app(AccountingPostingService::class)->postOwnerTransaction($transaction);
        $pnl = app(AccountingReportService::class)->profitAndLoss(['range' => 'today']);
        $this->assertSame(9000, $pnl['net_profit']);

        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.overview'))->assertForbidden();
    }

    private function paidOrder(array $overrides = []): Order
    {
        $product = Product::create(['name' => 'Accounting Piece '.Str::random(4), 'description' => 'Test', 'price' => '100.00', 'cost_price' => '40.00', 'stock' => 5, 'status' => 'active']);
        $number = 'CB-ACC-'.Str::upper(Str::random(8));
        $order = Order::create(array_merge(['number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64), 'customer_name' => 'Accounting Customer', 'customer_email' => 'accounting@example.test', 'shipping_address' => ['country' => 'Malaysia'], 'subtotal' => '100.00', 'original_shipping_fee' => '10.00', 'shipping_fee' => '10.00', 'discount_amount' => '10.00', 'free_shipping_discount' => '0.00', 'gift_wrapping' => true, 'gift_wrapping_fee' => '30.00', 'total' => '130.00', 'payment_method' => 'duitnow', 'payment_provider' => 'duitnow', 'payment_status' => 'paid', 'order_status' => 'pending', 'status' => 'pending'], $overrides));
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => '100.00', 'unit_cost' => '40.00', 'total' => '100.00', 'line_total' => '100.00']);
        return $order->fresh('items');
    }
}
