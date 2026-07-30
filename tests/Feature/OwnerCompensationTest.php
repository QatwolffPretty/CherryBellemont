<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\OwnerTransaction;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\GeneralLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerCompensationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingAccountService::class)->ensureDefaults();
        Storage::fake('local');
    }

    public function test_authorised_admin_can_open_owner_compensation_and_customer_cannot(): void
    {
        $this->actingAs($this->admin())->get(route('admin.accounting.owner-transactions.index'))->assertOk()->assertSee('Owner Compensation');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.owner-transactions.index'))->assertForbidden();
    }

    public function test_admin_can_create_every_owner_compensation_draft_with_server_resolved_accounts(): void
    {
        $admin = $this->admin();
        foreach (['salary', 'drawing', 'capital_contribution', 'business_reserve', 'emergency_reserve'] as $type) {
            $payload = $this->payload($type);
            $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.store'), $payload)->assertRedirect();
        }

        $this->assertDatabaseCount('owner_transactions', 5);
        $this->assertDatabaseHas('owner_transactions', ['transaction_type' => 'salary', 'debit_account_id' => $this->account('6100')->id, 'credit_account_id' => $this->bank()->id, 'status' => 'draft']);
        $this->assertDatabaseHas('owner_transactions', ['transaction_type' => 'drawing', 'debit_account_id' => $this->account('3100')->id, 'credit_account_id' => $this->bank()->id]);
        $this->assertDatabaseHas('owner_transactions', ['transaction_type' => 'capital_contribution', 'debit_account_id' => $this->bank()->id, 'credit_account_id' => $this->account('3000')->id]);
        $this->assertDatabaseHas('owner_transactions', ['transaction_type' => 'business_reserve', 'debit_account_id' => $this->account('3200')->id, 'credit_account_id' => $this->account('3400')->id]);
        $this->assertDatabaseHas('owner_transactions', ['transaction_type' => 'emergency_reserve', 'debit_account_id' => $this->account('3200')->id, 'credit_account_id' => $this->account('3500')->id]);
    }

    public function test_zero_negative_and_missing_required_payment_account_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->from(route('admin.accounting.owner-transactions.create'))->post(route('admin.accounting.owner-transactions.store'), $this->payload('salary', ['amount' => '0.00', 'payment_account_id' => null]))
            ->assertSessionHasErrors(['amount', 'payment_account_id']);
        $this->actingAs($admin)->from(route('admin.accounting.owner-transactions.create'))->post(route('admin.accounting.owner-transactions.store'), $this->payload('drawing', ['amount' => '-1.00']))
            ->assertSessionHasErrors('amount');
    }

    public function test_salary_posts_a_balanced_journal_and_appears_in_the_general_ledger(): void
    {
        $admin = $this->admin();
        $transaction = $this->draft($admin, 'salary', '25.00');

        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.post', $transaction))->assertSessionHas('success');
        $transaction = $transaction->fresh('journalEntry.lines');
        $this->assertSame('posted', $transaction->status);
        $this->assertSame('25.00', $transaction->journalEntry->total_debit);
        $this->assertSame('25.00', $transaction->journalEntry->total_credit);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $transaction->journal_entry_id, 'account_id' => $this->account('6100')->id, 'debit' => '25.00', 'credit' => '0.00']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $transaction->journal_entry_id, 'account_id' => $this->bank()->id, 'debit' => '0.00', 'credit' => '25.00']);
        $ledger = app(GeneralLedgerService::class)->accountLedger($this->account('6100'), ['range' => 'custom', 'from_date' => now()->startOfYear()->toDateString(), 'to_date' => now()->endOfYear()->toDateString()]);
        $this->assertSame(2500, $ledger['total_debit']);
        $this->actingAs($admin)->get(route('admin.accounting.owner-transactions.show', $transaction))->assertOk()->assertSee($transaction->journalEntry->entry_number);
    }

    public function test_drawings_capital_and_reserve_post_to_their_correct_accounts(): void
    {
        $admin = $this->admin();
        $this->bank()->update(['opening_balance' => '200.00', 'opening_balance_date' => now()->startOfYear()]);
        $this->account('3200')->update(['opening_balance' => '200.00', 'opening_balance_date' => now()->startOfYear()]);

        $drawing = $this->draft($admin, 'drawing', '30.00');
        $capital = $this->draft($admin, 'capital_contribution', '40.00');
        $businessReserve = $this->draft($admin, 'business_reserve', '50.00');
        $emergencyReserve = $this->draft($admin, 'emergency_reserve', '25.00');
        foreach ([$drawing, $capital, $businessReserve, $emergencyReserve] as $transaction) {
            $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.post', $transaction))->assertSessionHas('success');
        }

        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $drawing->fresh()->journal_entry_id, 'account_id' => $this->account('3100')->id, 'debit' => '30.00']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $capital->fresh()->journal_entry_id, 'account_id' => $this->account('3000')->id, 'credit' => '40.00']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $businessReserve->fresh()->journal_entry_id, 'account_id' => $this->account('3200')->id, 'debit' => '50.00']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $businessReserve->fresh()->journal_entry_id, 'account_id' => $this->account('3400')->id, 'credit' => '50.00']);
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $emergencyReserve->fresh()->journal_entry_id, 'account_id' => $this->account('3500')->id, 'credit' => '25.00']);
        $this->assertSame(5000, app(GeneralLedgerService::class)->accountLedger($this->account('3400'), $this->year())['closing_balance']);
        $this->assertSame(2500, app(GeneralLedgerService::class)->accountLedger($this->account('3500'), $this->year())['closing_balance']);
    }

    public function test_posted_transaction_is_immutable_idempotent_and_reversal_is_balanced(): void
    {
        $admin = $this->admin();
        $transaction = $this->draft($admin, 'salary', '15.00');
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.post', $transaction));
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.post', $transaction))->assertSessionHasErrors('transaction');
        $this->assertSame(1, JournalEntry::query()->count());
        $this->actingAs($admin)->get(route('admin.accounting.owner-transactions.edit', $transaction))->assertStatus(409);

        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.reverse', $transaction), ['reason' => 'Correction'])->assertSessionHas('success');
        $original = $transaction->fresh('journalEntry');
        $reversal = $original->reversalTransaction;
        $this->assertSame('reversed', $original->status);
        $this->assertNotNull($reversal);
        $this->assertSame('reversed', $reversal->status);
        $this->assertSame('posted', $reversal->journalEntry->status);
        $this->assertSame($reversal->journalEntry->total_debit, $reversal->journalEntry->total_credit);
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.reverse', $original))->assertSessionHasErrors('transaction');
    }

    public function test_drawings_and_reserve_allocations_cannot_exceed_posted_ledger_balances(): void
    {
        $admin = $this->admin();
        $drawing = $this->draft($admin, 'drawing', '10.00');
        $reserve = $this->draft($admin, 'business_reserve', '10.00');

        $this->actingAs($admin)->get(route('admin.accounting.owner-transactions.show', $drawing))->assertOk()->assertSee('Posting is blocked');
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.post', $drawing))->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.post', $reserve))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_cancelled_draft_creates_no_journal_and_private_attachment_download_is_authorised(): void
    {
        $admin = $this->admin();
        $payload = $this->payload('salary', ['attachment' => UploadedFile::fake()->create('salary-proof.pdf', 120, 'application/pdf')]);
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.store'), $payload)->assertRedirect();
        $transaction = OwnerTransaction::query()->firstOrFail();
        Storage::disk('local')->assertExists($transaction->attachment_path);
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.owner-transactions.attachment', $transaction))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.accounting.owner-transactions.attachment', $transaction))->assertOk();
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.cancel', $transaction))->assertSessionHas('success');
        $this->assertSame('cancelled', $transaction->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_export_requires_admin_and_existing_accounting_routes_still_render(): void
    {
        $admin = $this->admin();
        $this->draft($admin, 'capital_contribution', '10.00');
        $this->actingAs($admin)->get(route('admin.accounting.owner-transactions.export', ['format' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.owner-transactions.export', ['format' => 'csv']))->assertForbidden();
        foreach (['accounts.index', 'journals.index', 'ledger.index', 'income.index', 'expenses.index', 'profit-loss.index'] as $route) {
            $this->actingAs($admin)->get(route('admin.accounting.'.$route))->assertOk();
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function draft(User $admin, string $type, string $amount): OwnerTransaction
    {
        $this->actingAs($admin)->post(route('admin.accounting.owner-transactions.store'), $this->payload($type, ['amount' => $amount]));

        return OwnerTransaction::query()->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(string $type, array $overrides = []): array
    {
        return array_merge([
            'transaction_date' => now()->toDateString(),
            'transaction_type' => $type,
            'amount' => '20.00',
            'payment_account_id' => in_array($type, ['business_reserve', 'emergency_reserve'], true) ? null : $this->bank()->id,
            'description' => 'Owner compensation test transaction',
            'payment_method' => 'Bank transfer',
            'reference_number' => 'OC-TEST',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function year(): array
    {
        return ['range' => 'custom', 'from_date' => now()->startOfYear()->toDateString(), 'to_date' => now()->endOfYear()->toDateString()];
    }

    private function bank(): AccountingAccount { return $this->account('1010'); }
    private function account(string $code): AccountingAccount { return AccountingAccount::query()->where('code', $code)->firstOrFail(); }
}
