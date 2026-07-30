<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingAuditLog;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AccountingAccountService;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_journal_entries_route_and_create_a_balanced_draft(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.accounting.journals.index'))->assertOk()->assertSee('Journal Entries');

        $response = $this->actingAs($admin)->post(route('admin.accounting.journals.store'), $this->payload());
        $entry = JournalEntry::query()->firstOrFail();

        $response->assertRedirect(route('admin.accounting.journals.show', $entry));
        $this->assertMatchesRegularExpression('/^JE-\d{4}-\d{6}$/', $entry->entry_number);
        $this->assertSame('draft', $entry->status);
        $this->assertSame('50.00', $entry->total_debit);
        $this->assertSame('50.00', $entry->total_credit);
        $this->assertDatabaseHas('accounting_audit_logs', ['action' => 'journal.created', 'record_id' => $entry->id]);
    }

    public function test_unbalanced_or_invalid_lines_are_rejected(): void
    {
        $admin = $this->admin();
        $payload = $this->payload();
        $payload['lines'][1]['credit'] = '40.00';

        $this->actingAs($admin)->from(route('admin.accounting.journals.create'))->post(route('admin.accounting.journals.store'), $payload)
            ->assertRedirect(route('admin.accounting.journals.create'))
            ->assertSessionHasErrors('lines');

        $payload = $this->payload();
        $payload['lines'][0]['debit'] = '0.00';
        $this->actingAs($admin)->from(route('admin.accounting.journals.create'))->post(route('admin.accounting.journals.store'), $payload)
            ->assertSessionHasErrors('lines.0');
    }

    public function test_draft_is_editable_but_posted_journal_is_immutable(): void
    {
        $admin = $this->admin();
        $entry = $this->draft($admin);
        $payload = $this->payload(['description' => 'Updated manual adjustment', 'reference' => 'ADJ-002']);

        $this->actingAs($admin)->put(route('admin.accounting.journals.update', $entry), $payload)->assertRedirect(route('admin.accounting.journals.show', $entry));
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'description' => 'Updated manual adjustment', 'reference' => 'ADJ-002']);

        $this->actingAs($admin)->post(route('admin.accounting.journals.post', $entry))->assertSessionHas('success');
        $this->assertSame('posted', $entry->fresh()->status);
        $this->actingAs($admin)->get(route('admin.accounting.journals.edit', $entry))->assertStatus(409);
    }

    public function test_posting_is_idempotent_and_reversal_swaps_debits_and_credits(): void
    {
        $admin = $this->admin();
        $entry = $this->draft($admin);
        $this->actingAs($admin)->post(route('admin.accounting.journals.post', $entry));
        $this->actingAs($admin)->post(route('admin.accounting.journals.post', $entry));
        $this->assertSame(1, JournalEntry::query()->where('id', $entry->id)->count());

        $this->actingAs($admin)->post(route('admin.accounting.journals.reverse', $entry), ['reason' => 'Correction'])->assertRedirect();
        $original = $entry->fresh('lines');
        $reversal = JournalEntry::query()->findOrFail($original->reversal_entry_id)->load('lines');

        $this->assertSame('reversed', $original->status);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame($reversal->total_debit, $reversal->total_credit);
        $this->assertSame('0.00', $reversal->lines->firstWhere('account_id', $original->lines->first()->account_id)->debit);
        $this->assertSame('50.00', $reversal->lines->firstWhere('account_id', $original->lines->first()->account_id)->credit);
        $this->assertDatabaseHas('accounting_audit_logs', ['action' => 'journal.reversed', 'record_id' => $original->id]);
    }

    public function test_journal_numbers_are_unique_and_draft_can_be_cancelled(): void
    {
        $admin = $this->admin();
        $first = $this->draft($admin);
        $second = $this->draft($admin);
        $this->assertNotSame($first->entry_number, $second->entry_number);

        $this->actingAs($admin)->post(route('admin.accounting.journals.cancel', $first))->assertSessionHas('success');
        $this->assertSame('cancelled', $first->fresh()->status);
    }

    public function test_non_admin_cannot_manage_journals(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.accounting.journals.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.accounting.journals.store'), $this->payload())->assertForbidden();
    }

    private function admin(): User
    {
        app(AccountingAccountService::class)->ensureDefaults();

        return User::factory()->create(['is_admin' => true]);
    }

    private function draft(User $admin): JournalEntry
    {
        return app(JournalPostingService::class)->createDraft($this->payload(), $this->payload()['lines'], $admin->id);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        app(AccountingAccountService::class)->ensureDefaults();

        return array_merge([
            'transaction_date' => now()->toDateString(),
            'reference' => 'ADJ-001',
            'description' => 'Manual accounting adjustment',
            'lines' => [
                ['account_id' => AccountingAccount::query()->where('code', '1010')->value('id'), 'description' => 'Bank movement', 'debit' => '50.00', 'credit' => '0.00'],
                ['account_id' => AccountingAccount::query()->where('code', '4030')->value('id'), 'description' => 'Other income', 'debit' => '0.00', 'credit' => '50.00'],
            ],
        ], $overrides);
    }
}
