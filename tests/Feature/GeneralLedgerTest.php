<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\GeneralLedgerService;
use App\Services\JournalPostingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
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

    public function test_authorised_admin_can_open_general_ledger_and_non_admin_cannot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.index'))->assertOk()->assertSee('General Ledger');
        $this->actingAs($customer)->get(route('admin.accounting.ledger.index'))->assertForbidden();
    }

    public function test_only_posted_and_reversed_journal_history_affects_the_ledger(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $draft = $this->draft('2026-07-10', '25.00');
        $this->assertSame(0, app(GeneralLedgerService::class)->accountLedger($this->bank(), $this->period())['rows']->total());

        app(JournalPostingService::class)->post($draft, $admin->id);
        $this->assertSame(1, app(GeneralLedgerService::class)->accountLedger($this->bank(), $this->period())['rows']->total());

        $reversal = app(JournalPostingService::class)->reverse($draft->fresh(), $admin->id, 'Correction');
        $ledger = app(GeneralLedgerService::class)->accountLedger($this->bank(), $this->period());

        $this->assertSame('posted', $reversal->status);
        $this->assertSame('reversed', $draft->fresh()->status);
        $this->assertSame(2, $ledger['rows']->total());
        $this->assertSame(0, $ledger['closing_balance']);
    }

    public function test_debit_and_credit_normal_accounts_use_their_own_running_balance_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $bank = $this->bank();
        $bank->update(['opening_balance' => '100.00', 'opening_balance_date' => '2026-01-01']);
        $this->postEntry($this->draft('2026-07-10', '75.00'), $admin);

        $revenue = $this->revenue();
        $bankLedger = app(GeneralLedgerService::class)->accountLedger($bank->fresh(), $this->period());
        $revenueLedger = app(GeneralLedgerService::class)->accountLedger($revenue, $this->period());

        $this->assertSame(10000, $bankLedger['opening_balance']);
        $this->assertSame(17500, $bankLedger['closing_balance']);
        $this->assertSame(7500, $revenueLedger['closing_balance']);
        $this->assertStringContainsString('Dr', app(GeneralLedgerService::class)->balanceLabel($bankLedger['closing_balance'], $bank));
        $this->assertStringContainsString('Cr', app(GeneralLedgerService::class)->balanceLabel($revenueLedger['closing_balance'], $revenue));
    }

    public function test_opening_balance_in_selected_period_is_shown_once_as_a_configuration_row(): void
    {
        $bank = $this->bank();
        $bank->update(['opening_balance' => '40.00', 'opening_balance_date' => '2026-07-15']);

        $ledger = app(GeneralLedgerService::class)->accountLedger($bank->fresh(), $this->period());

        $this->assertSame(0, $ledger['opening_balance']);
        $this->assertSame(4000, $ledger['closing_balance']);
        $this->assertSame('opening', $ledger['rows']->first()['status']);
        $this->assertSame(1, $ledger['rows']->total());
    }

    public function test_account_summary_and_trial_balance_use_posted_journal_lines_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $posted = $this->draft('2026-07-10', '50.00');
        $draft = $this->draft('2026-07-11', '90.00');
        $this->postEntry($posted, $admin);

        $service = app(GeneralLedgerService::class);
        $overview = $service->overview($this->period());
        $bank = $overview['rows']->first(fn (array $row) => $row['account']->id === $this->bank()->id);
        $trialBalance = $service->trialBalance($this->period());

        $this->assertSame(5000, $bank['total_debit']);
        $this->assertSame(0, $bank['total_credit']);
        $this->assertSame(0, $overview['metrics']['total_debits'] - $overview['metrics']['total_credits']);
        $this->assertSame(0, $trialBalance['difference']);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_cancelled_journals_and_inactive_account_filters_preserve_valid_history_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $posted = $this->draft('2026-07-10', '50.00');
        $cancelled = $this->draft('2026-07-11', '30.00');
        $this->postEntry($posted, $admin);
        app(JournalPostingService::class)->cancel($cancelled, $admin->id);

        $bank = $this->bank();
        $bank->update(['is_active' => false]);
        $service = app(GeneralLedgerService::class);

        $this->assertSame(1, $service->accountLedger($bank, $this->period())['rows']->total());
        $this->assertSame(0, $service->overview($this->period(['status' => 'active']))['rows']->where('account.id', $bank->id)->count());
        $this->assertSame(1, $service->overview($this->period(['status' => 'inactive']))['rows']->where('account.id', $bank->id)->count());
    }

    public function test_parent_and_child_account_rows_do_not_double_count_and_date_filters_are_applied(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $child = AccountingAccount::query()->create([
            'code' => '1011',
            'name' => 'Savings Account',
            'type' => 'asset',
            'subtype' => 'Bank',
            'normal_balance' => 'debit',
            'parent_id' => $this->bank()->id,
            'opening_balance' => '0.00',
            'is_active' => true,
            'allow_manual_posting' => true,
        ]);
        $entry = app(JournalPostingService::class)->createDraft([
            'transaction_date' => '2026-07-10', 'description' => 'Child account entry',
        ], [
            ['account_id' => $child->id, 'debit' => '25.00', 'credit' => '0.00'],
            ['account_id' => $this->revenue()->id, 'debit' => '0.00', 'credit' => '25.00'],
        ]);
        $this->postEntry($entry, $admin);

        $overview = app(GeneralLedgerService::class)->overview($this->period());
        $parent = $overview['rows']->first(fn (array $row) => $row['account']->id === $this->bank()->id);
        $childRow = $overview['rows']->first(fn (array $row) => $row['account']->id === $child->id);

        $this->assertSame(0, $parent['total_debit']);
        $this->assertSame(2500, $childRow['total_debit']);
        $june = app(GeneralLedgerService::class)->overview(['range' => 'custom', 'from_date' => '2026-06-01', 'to_date' => '2026-06-30']);
        $this->assertSame(0, $june['rows']->first(fn (array $row) => $row['account']->id === $child->id)['total_debit']);
    }

    public function test_account_ledger_pagination_carries_forward_running_balance(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (['10.00', '20.00', '30.00'] as $index => $amount) {
            $this->postEntry($this->draft('2026-07-'.(10 + $index), $amount), $admin);
        }

        $service = app(GeneralLedgerService::class);
        $firstPage = $service->accountLedger($this->bank(), $this->period(['page' => 1]), 2);
        $secondPage = $service->accountLedger($this->bank(), $this->period(['page' => 2]), 2);

        $this->assertSame(2, $firstPage['rows']->count());
        $this->assertSame(1, $secondPage['rows']->count());
        $this->assertSame(6000, $secondPage['rows']->first()['running_balance']);
    }

    public function test_integrity_checks_detect_an_unbalanced_posted_journal_without_altering_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $entry = JournalEntry::query()->create([
            'entry_number' => 'JE-2026-999999',
            'transaction_date' => '2026-07-12',
            'posting_date' => now(),
            'description' => 'Legacy imbalance for diagnostics',
            'status' => 'posted',
            'total_debit' => '10.00',
            'total_credit' => '5.00',
            'posted_at' => now(),
            'posted_by' => $admin->id,
        ]);
        $entry->lines()->create(['account_id' => $this->bank()->id, 'debit' => '10.00', 'credit' => '0.00']);
        $entry->lines()->create(['account_id' => $this->revenue()->id, 'debit' => '0.00', 'credit' => '5.00']);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.integrity'))->assertOk()->assertSee('Unbalanced posted journal');
        $this->assertSame('posted', $entry->fresh()->status);
    }

    public function test_csv_export_uses_selected_period_and_existing_accounting_pages_still_open(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->postEntry($this->draft('2026-07-10', '10.00'), $admin);

        $this->actingAs($admin)->get(route('admin.accounting.ledger.export', ['format' => 'csv', 'range' => 'custom', 'from_date' => '2026-07-01', 'to_date' => '2026-07-31']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($admin)->get(route('admin.accounting.accounts.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.journals.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.income.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.expenses.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.profit-loss.index'))->assertOk();
    }

    private function postEntry(JournalEntry $entry, User $admin): void
    {
        app(JournalPostingService::class)->post($entry, $admin->id);
    }

    private function draft(string $date, string $amount): JournalEntry
    {
        return app(JournalPostingService::class)->createDraft([
            'transaction_date' => $date,
            'reference' => 'GL-'.str_replace('.', '', $amount),
            'description' => 'General ledger test entry',
        ], [
            ['account_id' => $this->bank()->id, 'debit' => $amount, 'credit' => '0.00'],
            ['account_id' => $this->revenue()->id, 'debit' => '0.00', 'credit' => $amount],
        ]);
    }

    /** @return array<string, mixed> */
    private function period(array $extra = []): array
    {
        return array_merge(['range' => 'custom', 'from_date' => '2026-07-01', 'to_date' => '2026-07-31'], $extra);
    }

    private function bank(): AccountingAccount
    {
        return AccountingAccount::query()->where('code', '1010')->firstOrFail();
    }

    private function revenue(): AccountingAccount
    {
        return AccountingAccount::query()->where('code', '4030')->firstOrFail();
    }
}
