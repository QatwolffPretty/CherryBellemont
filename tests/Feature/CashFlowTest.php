<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\CashFlowService;
use App\Services\JournalPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-07-30 10:00:00');
        app(AccountingAccountService::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_authorised_admin_can_open_cash_flow_and_customer_cannot(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.accounting.cash-flow.index'))->assertOk()->assertSee('Cash Flow');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.cash-flow.index'))->assertForbidden();
    }

    public function test_only_posted_cash_activity_is_reported_and_draft_and_cancelled_journals_are_excluded(): void
    {
        $admin = $this->admin();
        $posted = $this->draft('order', [$this->line('1010', '100.00'), $this->line('4000', '0.00', '100.00')]);
        $draft = $this->draft('order', [$this->line('1010', '50.00'), $this->line('4000', '0.00', '50.00')]);
        $cancelled = $this->draft('order', [$this->line('1010', '25.00'), $this->line('4000', '0.00', '25.00')]);
        app(JournalPostingService::class)->post($posted, $admin->id);
        app(JournalPostingService::class)->cancel($cancelled, $admin->id);

        $report = $this->report();
        $this->assertSame(10000, $report['cash_inflow']);
        $this->assertSame(10000, $report['closing_cash']);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_direct_method_classifies_customer_receipts_expenses_owner_salary_drawings_and_capital_correctly(): void
    {
        $admin = $this->admin();
        $this->postJournal($admin, 'order', [$this->line('1010', '100.00'), $this->line('4000', '0.00', '100.00')]);
        $this->postJournal($admin, 'expense', [$this->line('6030', '25.00'), $this->line('1010', '0.00', '25.00')]);
        $this->postJournal($admin, 'owner_compensation', [$this->line('6100', '10.00'), $this->line('1010', '0.00', '10.00')]);
        $this->postJournal($admin, 'owner_compensation', [$this->line('3100', '20.00'), $this->line('1010', '0.00', '20.00')]);
        $this->postJournal($admin, 'owner_compensation', [$this->line('1010', '50.00'), $this->line('3000', '0.00', '50.00')]);
        $this->postJournal($admin, 'owner_compensation', [$this->line('3200', '30.00'), $this->line('3400', '0.00', '30.00')]);

        $report = $this->report();
        $this->assertSame(6500, $report['metrics']['operating']);
        $this->assertSame(3000, $report['metrics']['financing']);
        $this->assertSame(9500, $report['closing_cash']);
        $this->assertSame(1000, $report['metrics']['salary']);
        $this->assertSame(2000, $report['metrics']['drawings']);
        $this->assertSame(5000, $report['metrics']['capital']);
        $this->assertSame(0, $report['filtered_movements']->where('source_type', 'owner_compensation')->where('category', 'reserve_transfer')->count());
    }

    public function test_internal_transfers_are_eliminated_from_consolidated_cash_flow_but_visible_for_a_selected_account(): void
    {
        $admin = $this->admin();
        $this->postJournal($admin, 'manual_journal', [$this->line('1010', '100.00'), $this->line('1000', '0.00', '100.00')]);

        $consolidated = $this->report();
        $bank = $this->account('1010');
        $accountReport = app(CashFlowService::class)->account($bank, $this->period());

        $this->assertSame(0, $consolidated['cash_inflow']);
        $this->assertSame(0, $consolidated['cash_outflow']);
        $this->assertSame(0, $consolidated['closing_cash']);
        $this->assertSame(10000, $accountReport['closing_cash']);
        $this->assertTrue($accountReport['all_movements']->first()['is_internal_transfer']);
    }

    public function test_stripe_settlement_is_not_double_counted_and_fee_and_refund_are_operating_outflows(): void
    {
        $admin = $this->admin();
        $this->postJournal($admin, 'order', [$this->line('1020', '100.00'), $this->line('4000', '0.00', '100.00')]);
        $this->postJournal($admin, 'settlement', [$this->line('1010', '100.00'), $this->line('1020', '0.00', '100.00')]);
        $this->postJournal($admin, 'payment_fee', [$this->line('6000', '5.00'), $this->line('1020', '0.00', '5.00')]);
        $this->postJournal($admin, 'refund', [$this->line('4100', '20.00'), $this->line('1020', '0.00', '20.00')]);

        $report = $this->report();
        $this->assertSame(10000, $report['cash_inflow']);
        $this->assertSame(2500, $report['cash_outflow']);
        $this->assertSame(7500, $report['closing_cash']);
        $this->assertSame(500, $report['metrics']['fees']);
        $this->assertSame(2000, $report['metrics']['refunds']);
        $this->assertSame(0, $report['reconciliation_difference']);
    }

    public function test_accruals_without_a_cash_line_are_excluded_while_supplier_payment_is_operating_cash_flow(): void
    {
        $admin = $this->admin();
        $this->postJournal($admin, 'expense_accrual', [$this->line('6030', '12.00'), $this->line('2000', '0.00', '12.00')]);
        $this->postJournal($admin, 'supplier_payment', [$this->line('2000', '12.00'), $this->line('1010', '0.00', '12.00')]);

        $report = $this->report();
        $this->assertSame(1200, $report['cash_outflow']);
        $this->assertSame('supplier_payments', $report['filtered_movements']->first()['category']);
    }

    public function test_opening_balance_and_reversal_reconcile_to_general_ledger_cash(): void
    {
        $admin = $this->admin();
        $this->account('1010')->update(['opening_balance' => '100.00', 'opening_balance_date' => '2026-01-01']);
        $entry = $this->postJournal($admin, 'order', [$this->line('1010', '50.00'), $this->line('4000', '0.00', '50.00')]);
        app(JournalPostingService::class)->reverse($entry, $admin->id, 'Correction');

        $report = $this->report();
        $this->assertSame(10000, $report['opening_cash']);
        $this->assertSame(10000, $report['closing_cash']);
        $this->assertSame(0, $report['reconciliation_difference']);
        $this->assertCount(2, $report['filtered_movements']);
    }

    public function test_filters_diagnostics_and_exports_are_available_to_admins_only(): void
    {
        $admin = $this->admin();
        $this->postJournal($admin, 'manual_journal', [$this->line('1010', '33.00'), $this->line('3300', '0.00', '33.00')]);
        $service = app(CashFlowService::class);
        $filtered = $service->report($this->period(['classification' => 'unclassified']));

        $this->assertSame(1, $filtered['filtered_movements']->count());
        $this->assertNotEmpty($service->diagnostics($this->period())->where('title', 'Unclassified cash movement'));
        $this->actingAs($admin)->get(route('admin.accounting.cash-flow.export.statement', ['format' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.cash-flow.diagnostics'))->assertForbidden();
    }

    public function test_missing_cash_account_configuration_shows_setup_guidance_and_admin_mapping_changes_are_audited(): void
    {
        $admin = $this->admin();
        AccountingAccount::query()->update(['cash_flow_enabled' => false]);
        $this->actingAs($admin)->get(route('admin.accounting.cash-flow.index'))->assertOk()->assertSee('Cash account setup required');

        $account = $this->account('6190');
        $this->actingAs($admin)->put(route('admin.accounting.cash-flow.configuration.update'), ['mappings' => [[
            'accounting_account_id' => $account->id,
            'classification' => 'operating',
            'category_key' => 'other_operating_payments',
            'label' => 'Other Operating Payments',
            'display_order' => 140,
            'is_active' => true,
        ]]])->assertSessionHas('success');

        $this->assertDatabaseHas('cash_flow_account_mappings', ['accounting_account_id' => $account->id, 'category_key' => 'other_operating_payments']);
        $this->assertDatabaseHas('accounting_audit_logs', ['action' => 'cash_flow.mapping.updated']);
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function draft(string $source, array $lines): \App\Models\JournalEntry
    {
        return app(JournalPostingService::class)->createDraft(['transaction_date' => '2026-07-10', 'source_type' => $source, 'description' => 'Cash Flow test '.$source], $lines);
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function postJournal(User $admin, string $source, array $lines): \App\Models\JournalEntry
    {
        $entry = $this->draft($source, $lines);
        return app(JournalPostingService::class)->post($entry, $admin->id);
    }

    /** @return array<string,mixed> */
    private function line(string $code, string $debit = '0.00', string $credit = '0.00'): array
    {
        return ['account_id' => $this->account($code)->id, 'debit' => $debit, 'credit' => $credit];
    }

    /** @return array<string,mixed> */
    private function period(array $extra = []): array
    {
        return array_merge(['range' => 'custom', 'from_date' => '2026-07-01', 'to_date' => '2026-07-31', 'include_clearing' => true, 'include_internal_transfers' => true], $extra);
    }

    private function report(): array
    {
        return app(CashFlowService::class)->report($this->period());
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::query()->where('code', $code)->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }
}
