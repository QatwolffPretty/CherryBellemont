<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Expense;
use App\Models\User;
use App\Services\AccountingAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorised_admin_can_open_the_chart_of_accounts_from_its_sidebar_route(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.accounting.accounts.index'));

        $response->assertOk()->assertSee('Chart of Accounts')->assertSee('Create Account');
        $this->assertDatabaseHas('accounting_accounts', ['code' => '1000', 'is_system' => true]);
    }

    public function test_admin_can_create_an_account_and_normal_balance_defaults_by_type(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.accounting.accounts.store'), [
            'code' => '8800',
            'name' => 'Studio Hire Expense',
            'type' => 'expense',
            'subtype' => 'Other Operating Expense',
            'opening_balance' => '0.00',
            'is_active' => true,
            'allow_manual_posting' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('accounting_accounts', ['code' => '8800', 'normal_balance' => 'debit', 'is_system' => false]);
    }

    public function test_duplicate_and_invalid_account_values_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.accounting.accounts.index'));

        $this->actingAs($admin)->from(route('admin.accounting.accounts.create'))->post(route('admin.accounting.accounts.store'), [
            'code' => '1000', 'name' => 'Duplicate', 'type' => 'asset', 'subtype' => 'Cash', 'normal_balance' => 'debit', 'opening_balance' => '0.00',
        ])->assertRedirect(route('admin.accounting.accounts.create'))->assertSessionHasErrors('code');

        $this->actingAs($admin)->from(route('admin.accounting.accounts.create'))->post(route('admin.accounting.accounts.store'), [
            'code' => 'A-100', 'name' => 'Invalid', 'type' => 'invalid', 'normal_balance' => 'debit', 'opening_balance' => '0.00',
        ])->assertRedirect(route('admin.accounting.accounts.create'))->assertSessionHasErrors(['code', 'type']);
    }

    public function test_parent_rules_prevent_self_parenting_and_incompatible_types(): void
    {
        $admin = $this->admin();
        $asset = $this->customAccount(['code' => '8810', 'type' => 'asset', 'subtype' => 'Cash']);
        $expense = $this->customAccount(['code' => '8811', 'type' => 'expense', 'subtype' => 'Office']);

        $this->actingAs($admin)->from(route('admin.accounting.accounts.edit', $asset))->put(route('admin.accounting.accounts.update', $asset), $this->payload($asset, ['parent_id' => $asset->id]))
            ->assertRedirect(route('admin.accounting.accounts.edit', $asset))->assertSessionHasErrors('parent_id');

        $this->actingAs($admin)->from(route('admin.accounting.accounts.edit', $expense))->put(route('admin.accounting.accounts.update', $expense), $this->payload($expense, ['parent_id' => $asset->id]))
            ->assertRedirect(route('admin.accounting.accounts.edit', $expense))->assertSessionHasErrors('parent_id');
    }

    public function test_opening_balance_requires_a_date_and_status_can_be_changed_for_custom_accounts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->from(route('admin.accounting.accounts.create'))->post(route('admin.accounting.accounts.store'), [
            'code' => '8820', 'name' => 'Opening Bank', 'type' => 'asset', 'subtype' => 'Bank', 'normal_balance' => 'debit', 'opening_balance' => '100.00',
        ])->assertRedirect(route('admin.accounting.accounts.create'))->assertSessionHasErrors('opening_balance_date');

        $account = $this->customAccount(['code' => '8821']);
        $this->actingAs($admin)->patch(route('admin.accounting.accounts.toggle-status', $account))->assertSessionHas('success');
        $this->assertFalse($account->fresh()->is_active);
    }

    public function test_system_and_referenced_accounts_cannot_be_deleted_but_unused_custom_accounts_can(): void
    {
        $admin = $this->admin();
        app(AccountingAccountService::class)->ensureDefaults();
        $system = AccountingAccount::query()->where('code', '1000')->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.accounting.accounts.destroy', $system))->assertSessionHasErrors('account');

        $referenced = $this->customAccount(['code' => '8830']);
        Expense::query()->create([
            'expense_number' => 'EXP-COA-001', 'expense_date' => now(), 'accounting_date' => now(), 'debit_account_id' => $referenced->id,
            'payment_account_id' => AccountingAccount::query()->where('code', '1010')->value('id'), 'description' => 'Referenced expense', 'amount' => '10.00', 'status' => 'draft',
        ]);
        $this->actingAs($admin)->delete(route('admin.accounting.accounts.destroy', $referenced))->assertSessionHasErrors('account');

        $unused = $this->customAccount(['code' => '8831']);
        $this->actingAs($admin)->delete(route('admin.accounting.accounts.destroy', $unused))->assertRedirect(route('admin.accounting.accounts.index'));
        $this->assertDatabaseMissing('accounting_accounts', ['id' => $unused->id]);
    }

    public function test_default_seeder_is_idempotent_and_non_admins_cannot_manage_accounts(): void
    {
        $service = app(AccountingAccountService::class);
        $service->ensureDefaults();
        $firstCount = AccountingAccount::query()->count();
        $service->ensureDefaults();

        $this->assertSame($firstCount, AccountingAccount::query()->count());
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('admin.accounting.accounts.index'))->assertForbidden();
    }

    public function test_income_expenses_and_profit_and_loss_routes_continue_to_render(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.accounting.income.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.expenses.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.accounting.profit-loss.index'))->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function customAccount(array $overrides = []): AccountingAccount
    {
        return AccountingAccount::query()->create(array_merge([
            'code' => '89'.fake()->unique()->numberBetween(100, 999), 'name' => 'Custom Account', 'type' => 'expense', 'subtype' => 'Other Operating Expense',
            'normal_balance' => 'debit', 'opening_balance' => '0.00', 'is_active' => true, 'is_system' => false, 'allow_manual_posting' => true,
        ], $overrides));
    }

    private function payload(AccountingAccount $account, array $overrides = []): array
    {
        return array_merge([
            'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'subtype' => $account->subtype,
            'normal_balance' => $account->normal_balance, 'opening_balance' => $account->opening_balance, 'opening_balance_date' => $account->opening_balance_date?->toDateString(),
            'is_active' => true, 'allow_manual_posting' => true,
        ], $overrides);
    }
}
