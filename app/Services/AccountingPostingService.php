<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\OwnerTransaction;
use App\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The sole writer for posted accounting journals. It receives confirmed business
 * events, creates balanced entries inside a transaction, and uses the source
 * triplet to make retries harmless.
 */
class AccountingPostingService
{
    public function __construct(
        private readonly AccountingAccountService $accounts,
        private readonly AccountingSettingsService $settings,
        private readonly AccountingAuditService $audit,
        private readonly JournalPostingService $journals,
    ) {}

    public function postPaidOrder(Order $order): ?JournalEntry
    {
        if ($order->payment_status !== 'paid' || ! $this->settings->automaticPostingEnabled()) {
            return null;
        }

        $this->accounts->ensureDefaults();
        $order->loadMissing('items');

        $cash = $this->accounts->mapped(($order->payment_provider ?: $order->payment_method) === 'stripe' ? 'stripe_clearing_account' : 'duitnow_clearing_account');
        $productSales = $this->accounts->mapped('product_sales_account');
        $shippingIncome = $this->accounts->mapped('shipping_income_account');
        $giftIncome = $this->accounts->mapped('gift_wrapping_income_account');
        $discounts = $this->accounts->mapped('sales_discount_account');
        $inventory = $this->accounts->mapped('inventory_asset_account');
        $cogs = $this->accounts->mapped('cost_of_goods_sold_account');

        $discount = $this->money($order->discount_amount) + $this->money($order->free_shipping_discount);
        $lines = [
            $this->line($cash, debit: $this->money($order->total), description: 'Customer receipt for '.$order->order_number, orderId: $order->id),
            $this->line($productSales, credit: $this->money($order->subtotal), description: 'Product sales for '.$order->order_number, orderId: $order->id),
            $this->line($shippingIncome, credit: $this->money($order->original_shipping_fee ?? $order->shipping_fee), description: 'Shipping income for '.$order->order_number, orderId: $order->id),
            $this->line($giftIncome, credit: $this->money($order->gift_wrapping_fee), description: 'Gift wrapping income for '.$order->order_number, orderId: $order->id),
        ];
        if ($discount > 0) {
            $lines[] = $this->line($discounts, debit: $discount, description: 'Discounts applied to '.$order->order_number, orderId: $order->id);
        }

        $entry = $this->createPostedEntry([
            'source_type' => 'order', 'source_id' => $order->id, 'source_event' => 'paid', 'transaction_date' => ($order->stripe_paid_at ?? $order->updated_at ?? now())->toDateString(),
            'reference' => $order->order_number, 'description' => 'Paid customer order '.$order->order_number, 'currency' => 'MYR',
        ], $lines);

        if (! $entry) {
            return null;
        }

        $cost = $order->items->sum(fn ($item) => $this->money($item->unit_cost) * (int) $item->quantity);
        if ($cost > 0) {
            $this->createPostedEntry([
                'source_type' => 'order', 'source_id' => $order->id, 'source_event' => 'cost_of_goods_sold', 'transaction_date' => ($order->stripe_paid_at ?? $order->updated_at ?? now())->toDateString(),
                'reference' => $order->order_number, 'description' => 'Cost of goods sold for '.$order->order_number, 'currency' => 'MYR',
            ], [
                $this->line($cogs, debit: $cost, description: 'Historical item cost', orderId: $order->id),
                $this->line($inventory, credit: $cost, description: 'Inventory released', orderId: $order->id),
            ]);
        }

        return $entry;
    }

    public function postPaymentFee(Order $order, string $providerTransactionId, mixed $feeAmount, ?int $userId = null): ?JournalEntry
    {
        $fee = $this->money($feeAmount);
        if ($fee <= 0) return null;
        $this->accounts->ensureDefaults();
        $expense = $this->accounts->mapped('payment_processing_fee_account');
        $clearing = $this->accounts->mapped(($order->payment_provider ?: $order->payment_method) === 'stripe' ? 'stripe_clearing_account' : 'duitnow_clearing_account');

        return $this->createPostedEntry([
            'source_type' => 'payment_fee', 'source_id' => $order->id, 'source_event' => $providerTransactionId,
            'transaction_date' => now()->toDateString(), 'reference' => $providerTransactionId, 'description' => 'Payment processing fee for '.$order->order_number, 'currency' => 'MYR', 'posted_by' => $userId,
        ], [$this->line($expense, debit: $fee, description: 'Provider fee', orderId: $order->id), $this->line($clearing, credit: $fee, description: 'Provider fee settlement', orderId: $order->id)]);
    }

    public function postCompletedRefund(Refund $refund, ?int $userId = null): ?JournalEntry
    {
        if ($refund->status !== 'succeeded') return null;
        $this->accounts->ensureDefaults();
        $refund->loadMissing('order', 'returnRequest.items.orderItem');
        $refundAccount = $this->accounts->mapped('refund_account');
        $cash = $this->accounts->mapped($refund->payment_provider === 'stripe' ? 'stripe_clearing_account' : 'duitnow_clearing_account');
        $entry = $this->createPostedEntry([
            'source_type' => 'refund', 'source_id' => $refund->id, 'source_event' => 'completed', 'transaction_date' => ($refund->confirmed_at ?? now())->toDateString(),
            'reference' => $refund->refund_number, 'description' => 'Completed refund '.$refund->refund_number, 'currency' => $refund->currency ?: 'MYR', 'posted_by' => $userId,
        ], [
            $this->line($refundAccount, debit: $this->money($refund->amount), description: 'Sales return / refund', orderId: $refund->order_id),
            $this->line($cash, credit: $this->money($refund->amount), description: 'Refund paid', orderId: $refund->order_id),
        ]);

        $restockedCost = (int) optional($refund->returnRequest)->items
            ? $refund->returnRequest->items->whereNotNull('restocked_at')->sum(fn ($item) => $this->money($item->orderItem?->unit_cost) * (int) ($item->approved_quantity ?? 0))
            : 0;
        if ($restockedCost > 0) {
            $inventory = $this->accounts->mapped('inventory_asset_account');
            $cogs = $this->accounts->mapped('cost_of_goods_sold_account');
            $this->createPostedEntry([
                'source_type' => 'refund', 'source_id' => $refund->id, 'source_event' => 'restocked_cost', 'transaction_date' => ($refund->confirmed_at ?? now())->toDateString(),
                'reference' => $refund->refund_number, 'description' => 'Restocked inventory cost for '.$refund->refund_number, 'currency' => $refund->currency ?: 'MYR', 'posted_by' => $userId,
            ], [$this->line($inventory, debit: $restockedCost, description: 'Returned inventory', orderId: $refund->order_id), $this->line($cogs, credit: $restockedCost, description: 'COGS reversal', orderId: $refund->order_id)]);
        }

        return $entry;
    }

    public function postExpense(Expense $expense, ?int $userId = null): JournalEntry
    {
        $this->accounts->ensureDefaults();
        return DB::transaction(function () use ($expense, $userId): JournalEntry {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->journal_entry_id) return $expense->journalEntry()->firstOrFail();
            $amount = $this->money($expense->amount) + $this->money($expense->tax_amount);
            $entry = $this->createPostedEntry([
                'source_type' => 'expense', 'source_id' => $expense->id, 'source_event' => 'posted', 'transaction_date' => $expense->accounting_date->toDateString(),
                'reference' => $expense->reference_number ?: $expense->expense_number, 'description' => $expense->description, 'currency' => 'MYR', 'posted_by' => $userId,
            ], [
                $this->line($expense->debitAccount()->firstOrFail(), debit: $amount, description: $expense->description, expenseId: $expense->id),
                $this->line($expense->paymentAccount()->firstOrFail(), credit: $amount, description: 'Expense payment', expenseId: $expense->id),
            ]);
            $expense->update(['status' => 'posted', 'payment_status' => 'paid', 'journal_entry_id' => $entry->id, 'approved_by' => $userId]);
            $this->audit->record('expense.posted', $expense, $userId, [], ['journal_entry_id' => $entry->id]);
            return $entry;
        }, 3);
    }

    public function postOwnerTransaction(OwnerTransaction $transaction, ?int $userId = null): JournalEntry
    {
        return app(OwnerCompensationPostingService::class)->post($transaction, $userId);
    }

    /** @param array<int, array<string, mixed>> $lines */
    public function createDraft(array $attributes, array $lines, ?int $userId = null): JournalEntry
    {
        return $this->journals->createDraft($attributes, $lines, $userId);
    }

    public function postDraft(JournalEntry $entry, ?int $userId = null): JournalEntry
    {
        return $this->journals->post($entry, $userId);
    }

    public function reverse(JournalEntry $entry, ?int $userId = null, ?string $reason = null): JournalEntry
    {
        return $this->journals->reverse($entry, $userId, $reason);
    }

    /** @param array<string, mixed> $attributes @param array<int, array<string, mixed>> $lines */
    private function createPostedEntry(array $attributes, array $lines): ?JournalEntry
    {
        $lines = array_values(array_filter($lines, fn (array $line): bool => $this->money($line['debit'] ?? 0) > 0 || $this->money($line['credit'] ?? 0) > 0));
        $this->assertBalanced($lines);
        try {
            return DB::transaction(function () use ($attributes, $lines): JournalEntry {
                $existing = JournalEntry::query()->where('source_type', $attributes['source_type'] ?? null)->where('source_id', $attributes['source_id'] ?? null)->where('source_event', $attributes['source_event'] ?? null)->lockForUpdate()->first();
                if ($existing) return $existing;
                $entry = JournalEntry::query()->create($attributes + [
                    'entry_number' => $this->entryNumber(), 'status' => 'posted', 'posting_date' => now(), 'posted_at' => now(),
                    'total_debit' => $this->decimal($this->sum($lines, 'debit')), 'total_credit' => $this->decimal($this->sum($lines, 'credit')),
                ]);
                $this->persistLines($entry, $lines);
                $this->audit->record('journal.posted', $entry, $attributes['posted_by'] ?? null, [], ['source_type' => $entry->source_type, 'source_event' => $entry->source_event]);
                return $entry;
            }, 3);
        } catch (QueryException) {
            return JournalEntry::query()->where('source_type', $attributes['source_type'] ?? null)->where('source_id', $attributes['source_id'] ?? null)->where('source_event', $attributes['source_event'] ?? null)->first();
        }
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function persistLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $line) {
            $line['debit'] = $this->decimal($this->money($line['debit'] ?? 0));
            $line['credit'] = $this->decimal($this->money($line['credit'] ?? 0));
            $entry->lines()->create($line);
        }
    }

    /** @return array<string, mixed> */
    private function line(AccountingAccount $account, int $debit = 0, int $credit = 0, ?string $description = null, ?int $orderId = null, ?int $expenseId = null, ?int $ownerTransactionId = null): array
    {
        return ['account_id' => $account->id, 'description' => $description, 'debit' => $debit, 'credit' => $credit, 'order_id' => $orderId, 'expense_id' => $expenseId, 'owner_transaction_id' => $ownerTransactionId];
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) throw new InvalidArgumentException('A journal entry requires at least two lines.');
        foreach ($lines as $line) {
            $debit = $this->money($line['debit'] ?? 0); $credit = $this->money($line['credit'] ?? 0);
            if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) throw new InvalidArgumentException('Every journal line must contain either a debit or a credit.');
        }
        if ($this->sum($lines, 'debit') !== $this->sum($lines, 'credit')) throw new InvalidArgumentException('Journal entries must balance before posting.');
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function sum(array $lines, string $key): int { return array_sum(array_map(fn (array $line) => $this->money($line[$key] ?? 0), $lines)); }
    private function money(mixed $amount): int
    {
        if (is_int($amount)) return $amount;
        $value = trim((string) ($amount ?? '0'));
        if ($value === '') return 0;
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }
        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');
        return $matches[1] === '-' ? -$cents : $cents;
    }
    private function decimal(int $amount): string { return number_format($amount / 100, 2, '.', ''); }
    private function entryNumber(): string { $lastId = (int) (JournalEntry::query()->lockForUpdate()->max('id') ?? 0) + 1; return (string) $this->settings->get('journal_entry_prefix', 'JE').'-'.now()->format('Y').'-'.str_pad((string) $lastId, 6, '0', STR_PAD_LEFT); }
}
