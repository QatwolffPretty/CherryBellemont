<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\OwnerTransaction;
use App\Models\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    /** @return array<string, string> */
    public function rangeOptions(bool $sales = false): array
    {
        return $sales
            ? ['today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This Week', 'last_week' => 'Last Week', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'this_quarter' => 'This Quarter', 'this_year' => 'This Year', 'custom' => 'Custom Date Range']
            : ['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'this_quarter' => 'This Quarter', 'this_year' => 'This Year', 'custom' => 'Custom Date Range'];
    }

    /** @param array<string, mixed> $filters */
    public function period(array $filters, bool $sales = false): array
    {
        $today = CarbonImmutable::today();
        $range = (string) ($filters['range'] ?? 'this_month');
        [$start, $end, $label] = match ($range) {
            'today' => [$today, $today->endOfDay(), 'Today'],
            'yesterday' => [$today->subDay(), $today->subDay()->endOfDay(), 'Yesterday'],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek(), 'This Week'],
            'last_week' => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek(), 'Last Week'],
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth(), 'Last Month'],
            'this_quarter' => [$today->firstOfQuarter(), $today->lastOfQuarter(), 'This Quarter'],
            'this_year' => [$today->startOfYear(), $today->endOfYear(), 'This Year'],
            'custom' => [CarbonImmutable::parse((string) $filters['from_date'])->startOfDay(), CarbonImmutable::parse((string) $filters['to_date'])->endOfDay(), 'Custom Range'],
            default => [$today->startOfMonth(), $today->endOfMonth(), 'This Month'],
        };
        return compact('range', 'start', 'end', 'label');
    }

    /** @param array<string, mixed> $filters */
    public function overview(array $filters): array
    {
        $period = $this->period($filters);
        $sales = $this->salesSummary($filters);
        $pnl = $this->profitAndLoss($filters);
        $cash = $this->cashFlow($filters);
        $expenses = $this->within(Expense::query(), $period, 'expense_date');
        $owners = $this->within(OwnerTransaction::query(), $period, 'transaction_date');
        return [
            'period' => $period,
            'cards' => [
                'gross_sales' => $sales['gross_sales'], 'discounts' => $sales['discounts'], 'refunds' => $sales['refunds'], 'net_sales' => $sales['net_sales'],
                'other_income' => $pnl['other_income'], 'cost_of_goods_sold' => $pnl['cost_of_goods_sold'], 'operating_expenses' => $pnl['operating_expenses'],
                'owner_compensation' => $this->money($owners->where('transaction_type', 'owner_salary')->sum('amount')), 'gross_profit' => $pnl['gross_profit'], 'net_profit' => $pnl['net_profit'],
                'business_cash' => $cash['closing_balance'], 'unposted_transactions' => JournalEntry::query()->where('status', 'draft')->count() + (clone $expenses)->where('status', 'draft')->count() + (clone $owners)->where('status', 'draft')->count(),
            ],
            'charts' => $this->overviewCharts($period),
            'recent' => [
                'journals' => JournalEntry::query()->with('lines.account')->latest('transaction_date')->limit(8)->get(),
                'expenses' => Expense::query()->latest('expense_date')->limit(8)->get(),
                'owner_transactions' => OwnerTransaction::query()->latest('transaction_date')->limit(8)->get(),
                'orders' => Order::query()->where('payment_status', 'paid')->latest()->limit(8)->get(),
                'refunds' => Refund::query()->where('status', 'succeeded')->latest('confirmed_at')->limit(8)->get(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function salesSummary(array $filters): array
    {
        $period = $this->period($filters, true);
        $orders = $this->within(Order::query()->where('payment_status', 'paid'), $period);
        $orderRows = (clone $orders)->get(['id', 'order_number', 'number', 'customer_name', 'customer_email', 'subtotal', 'original_shipping_fee', 'shipping_fee', 'gift_wrapping_fee', 'discount_amount', 'free_shipping_discount', 'total', 'payment_provider', 'payment_method', 'coupon_code', 'shipping_zone_id', 'delivery_method_id', 'courier_name', 'created_at']);
        $refunds = $this->within(Refund::query()->where('status', 'succeeded'), $period, 'confirmed_at')->get();
        $productSales = $this->money($orderRows->sum('subtotal'));
        $shipping = $this->money($orderRows->sum(fn (Order $order) => $order->original_shipping_fee ?? $order->shipping_fee));
        $gift = $this->money($orderRows->sum('gift_wrapping_fee'));
        $discount = $orderRows->sum(fn (Order $order) => $this->money($order->discount_amount) + $this->money($order->free_shipping_discount));
        $gross = $productSales + $shipping + $gift - $discount;
        $refundTotal = $this->money($refunds->sum('amount'));
        $net = $gross - $refundTotal;
        $cogs = $this->orderCost($orderRows->pluck('id'));

        return [
            'period' => $period, 'paid_order_count' => $orderRows->count(), 'gross_product_sales' => $productSales, 'shipping_income' => $shipping, 'gift_wrapping_income' => $gift,
            'discounts' => $discount, 'gross_sales' => $gross, 'refunds' => $refundTotal, 'net_sales' => $net,
            'average_order_value' => $orderRows->isEmpty() ? 0 : $this->money($orderRows->sum('total')) / $orderRows->count(), 'estimated_cost_of_goods_sold' => $cogs,
            'estimated_gross_profit' => $net - $cogs,
            'stripe_sales' => $this->money($orderRows->filter(fn (Order $order) => ($order->payment_provider ?: $order->payment_method) === 'stripe')->sum('total')),
            'duitnow_sales' => $this->money($orderRows->filter(fn (Order $order) => ($order->payment_provider ?: $order->payment_method) === 'duitnow')->sum('total')),
            'charts' => $this->salesCharts($period, $orderRows, $refunds),
            'breakdowns' => $this->salesBreakdowns($period, $orderRows, $refunds, $net),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function ledger(array $filters): array
    {
        $period = $this->period($filters);
        $query = JournalEntryLine::query()->with(['journalEntry.poster', 'account'])->whereHas('journalEntry', fn (Builder $entry) => $entry->posted()->whereBetween('transaction_date', [$period['start'], $period['end']]));
        $this->applyLedgerFilters($query, $filters);
        $lines = $query->get()->sortBy(fn (JournalEntryLine $line) => $line->journalEntry->transaction_date->format('Y-m-d').'-'.str_pad((string) $line->journal_entry_id, 10, '0', STR_PAD_LEFT).'-'.str_pad((string) $line->id, 10, '0', STR_PAD_LEFT));
        $accountQuery = AccountingAccount::query();
        if (filled($filters['account_id'] ?? null)) $accountQuery->whereKey($filters['account_id']);
        if (filled($filters['account_type'] ?? null)) $accountQuery->where('type', $filters['account_type']);
        $accounts = $accountQuery->get()->keyBy('id');
        $opening = $this->ledgerOpeningBalances($period, $filters, $accounts);
        $balances = $opening;
        $rows = $lines->map(function (JournalEntryLine $line) use (&$balances, $accounts): array {
            $account = $accounts->get($line->account_id); $before = $balances[$line->account_id] ?? 0;
            $movement = $account?->isDebitNormal() ? $this->money($line->debit) - $this->money($line->credit) : $this->money($line->credit) - $this->money($line->debit);
            $balances[$line->account_id] = $before + $movement;
            return ['line' => $line, 'running_balance' => $balances[$line->account_id]];
        });
        return ['period' => $period, 'rows' => $rows, 'opening' => array_sum($opening), 'total_debits' => $rows->sum(fn ($row) => $this->money($row['line']->debit)), 'total_credits' => $rows->sum(fn ($row) => $this->money($row['line']->credit)), 'closing' => array_sum($balances), 'accounts' => AccountingAccount::query()->active()->orderBy('code')->get()];
    }

    /** @param array<string, mixed> $filters */
    public function profitAndLoss(array $filters): array
    {
        $period = $this->period($filters);
        $accounts = AccountingAccount::query()->whereIn('type', ['revenue', 'cost_of_goods_sold', 'expense'])->orderBy('code')->get();
        $totals = $this->accountActivity($period, $accounts);
        $revenue = $accounts->where('type', 'revenue')->map(fn (AccountingAccount $a) => ['account' => $a, 'amount' => $totals[$a->id] ?? 0]);
        $cogs = $accounts->where('type', 'cost_of_goods_sold')->map(fn (AccountingAccount $a) => ['account' => $a, 'amount' => $totals[$a->id] ?? 0]);
        $expenses = $accounts->where('type', 'expense')->map(fn (AccountingAccount $a) => ['account' => $a, 'amount' => $totals[$a->id] ?? 0]);
        $contraRevenue = $revenue->filter(fn (array $row) => in_array($row['account']->code, ['4090', '4100'], true))->sum('amount');
        $netRevenue = $revenue->reject(fn (array $row) => in_array($row['account']->code, ['4090', '4100'], true))->sum('amount') - $contraRevenue;
        $cogsTotal = $cogs->sum('amount'); $expenseTotal = $expenses->sum('amount'); $other = $revenue->first(fn (array $row) => $row['account']->code === '4030'); $otherIncome = $other['amount'] ?? 0;
        return ['period' => $period, 'revenue' => $revenue, 'cost_of_goods_sold_lines' => $cogs, 'expenses' => $expenses, 'net_revenue' => $netRevenue, 'cost_of_goods_sold' => $cogsTotal, 'gross_profit' => $netRevenue - $cogsTotal, 'operating_expenses' => $expenseTotal, 'net_profit' => $netRevenue - $cogsTotal - $expenseTotal, 'other_income' => $otherIncome];
    }

    /** @param array<string, mixed> $filters */
    public function cashFlow(array $filters): array
    {
        $period = $this->period($filters);
        $cashAccounts = AccountingAccount::query()->whereIn('code', ['1000', '1010', '1020', '1030'])->get();
        $opening = $this->accountActivityBefore($period, $cashAccounts);
        $lines = JournalEntryLine::query()->with(['journalEntry', 'account', 'ownerTransaction'])->whereIn('account_id', $cashAccounts->pluck('id'))->whereHas('journalEntry', fn (Builder $entry) => $entry->posted()->whereBetween('transaction_date', [$period['start'], $period['end']]))->get();
        $inflow = $lines->sum(fn (JournalEntryLine $line) => $this->money($line->debit)); $outflow = $lines->sum(fn (JournalEntryLine $line) => $this->money($line->credit));
        $openingTotal = array_sum($opening); $closing = $openingTotal + $inflow - $outflow;
        return ['period' => $period, 'opening_balance' => $openingTotal, 'customer_receipts' => $this->cashMovementForSources($lines, 'order', 'debit'), 'other_operating_receipts' => $this->cashMovementForSources($lines, 'manual_income', 'debit'), 'owner_capital_contributions' => $this->cashMovementForOwner($lines, 'owner_capital', 'debit'), 'operating_expenses_paid' => $this->cashMovementForSources($lines, 'expense', 'credit'), 'refunds_paid' => $this->cashMovementForSources($lines, 'refund', 'credit'), 'owner_salary_paid' => $this->cashMovementForOwner($lines, 'owner_salary', 'credit'), 'owner_drawings' => $this->cashMovementForOwner($lines, 'owner_drawing', 'credit'), 'reserve_transfers' => $this->cashMovementForOwner($lines, 'reserve_allocation', 'credit'), 'cash_inflow' => $inflow, 'cash_outflow' => $outflow, 'net_cash_movement' => $inflow - $outflow, 'closing_balance' => $closing, 'accounts' => $cashAccounts];
    }

    private function overviewCharts(array $period): array
    {
        $sales = $this->salesCharts($period, $this->within(Order::query()->where('payment_status', 'paid'), $period)->get(), $this->within(Refund::query()->where('status', 'succeeded'), $period, 'confirmed_at')->get());
        $expenses = $this->within(Expense::query()->where('status', 'posted'), $period, 'accounting_date')->selectRaw('DATE(accounting_date) as date, SUM(amount + tax_amount) as amount')->groupBy('date')->pluck('amount', 'date');
        return ['labels' => $sales['labels'], 'sales' => $sales['gross'], 'refunds' => $sales['refunds'], 'net_profit' => collect($sales['gross'])->map(fn ($amount, $key) => $amount - ($sales['refunds'][$key] ?? 0) - $this->money($expenses[$sales['keys'][$key]] ?? 0))->all(), 'expenses' => collect($sales['keys'])->map(fn ($date) => $this->money($expenses[$date] ?? 0))->all()];
    }

    private function salesCharts(array $period, Collection $orders, Collection $refunds): array
    {
        $dates = $this->dates($period); $orderDates = $orders->groupBy(fn (Order $o) => $o->created_at->toDateString()); $refundDates = $refunds->groupBy(fn (Refund $r) => ($r->confirmed_at ?? $r->created_at)->toDateString());
        $rows = $dates->map(function (CarbonImmutable $date) use ($orderDates, $refundDates): array { $orders = $orderDates->get($date->toDateString(), collect()); return ['date' => $date->toDateString(), 'label' => $date->format('d M'), 'gross' => $orders->sum(fn (Order $o) => $this->money($o->subtotal) + $this->money($o->original_shipping_fee ?? $o->shipping_fee) + $this->money($o->gift_wrapping_fee) - $this->money($o->discount_amount) - $this->money($o->free_shipping_discount)), 'orders' => $orders->count(), 'refunds' => $this->money($refundDates->get($date->toDateString(), collect())->sum('amount'))]; });
        return ['keys' => $rows->pluck('date')->all(), 'labels' => $rows->pluck('label')->all(), 'gross' => $rows->pluck('gross')->all(), 'net' => $rows->map(fn ($r) => $r['gross'] - $r['refunds'])->all(), 'orders' => $rows->pluck('orders')->all(), 'refunds' => $rows->pluck('refunds')->all()];
    }

    private function salesBreakdowns(array $period, Collection $orders, Collection $refunds, int $net): array
    {
        $percent = fn (int $amount): float => $net === 0 ? 0 : round(($amount / $net) * 100, 1);
        $payment = $orders->groupBy(fn (Order $o) => strtoupper($o->payment_provider ?: $o->payment_method ?: 'Other'))
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'order_count' => $rows->count(),
                'gross_sales' => $this->money($rows->sum('total')),
                'net_sales' => $this->money($rows->sum('total')),
                'percentage' => $percent($this->money($rows->sum('total'))),
            ])->values();
        $products = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.payment_status', 'paid')->whereBetween('orders.created_at', [$period['start'], $period['end']])->selectRaw('COALESCE(order_items.product_name, order_items.name) as name, SUM(order_items.quantity) as units_sold, SUM(COALESCE(order_items.line_total, order_items.total)) as gross_sales')->groupBy('name')->orderByDesc('gross_sales')->limit(20)->get()->map(fn ($row) => ['name' => $row->name, 'units_sold' => (int) $row->units_sold, 'gross_sales' => $this->money($row->gross_sales), 'net_sales' => $this->money($row->gross_sales), 'percentage' => $percent($this->money($row->gross_sales))]);
        $categories = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->leftJoin('category_product', fn ($join) => $join->on('category_product.product_id', '=', 'order_items.product_id')->where('category_product.is_primary', true))->leftJoin('categories', 'categories.id', '=', 'category_product.category_id')->where('orders.payment_status', 'paid')->whereBetween('orders.created_at', [$period['start'], $period['end']])->selectRaw("COALESCE(categories.name, 'Uncategorised') as name, SUM(order_items.quantity) as units_sold, SUM(COALESCE(order_items.line_total, order_items.total)) as gross_sales")->groupByRaw("COALESCE(categories.name, 'Uncategorised')")->orderByDesc('gross_sales')->get()->map(fn ($row) => ['name' => $row->name, 'units_sold' => (int) $row->units_sold, 'gross_sales' => $this->money($row->gross_sales), 'net_sales' => $this->money($row->gross_sales), 'percentage' => $percent($this->money($row->gross_sales))]);
        return ['payment_methods' => $payment, 'products' => $products, 'categories' => $categories, 'customers' => $orders->groupBy(fn (Order $o) => strtolower((string) ($o->customer_email ?: $o->email)))->map(fn ($rows, $email) => ['name' => $rows->first()->customer_name ?: $email, 'order_count' => $rows->count(), 'gross_sales' => $this->money($rows->sum('total')), 'net_sales' => $this->money($rows->sum('total')), 'percentage' => $percent($this->money($rows->sum('total')))])->sortByDesc('net_sales')->take(20)->values(), 'shipping_zones' => $orders->groupBy('shipping_zone_id')->map(fn ($rows, $id) => ['name' => $id ? 'Shipping zone #'.$id : 'Pickup / no zone', 'order_count' => $rows->count(), 'net_sales' => $this->money($rows->sum('total'))])->values(), 'delivery_methods' => $orders->groupBy('delivery_method_id')->map(fn ($rows, $id) => ['name' => $id ? 'Delivery method #'.$id : 'Not specified', 'order_count' => $rows->count(), 'net_sales' => $this->money($rows->sum('total'))])->values(), 'discounts' => $orders->filter(fn (Order $o) => $o->coupon_code)->groupBy('coupon_code')->map(fn ($rows, $code) => ['name' => $code, 'uses' => $rows->count(), 'discounts' => $this->money($rows->sum('discount_amount'))])->values(), 'refunds' => $refunds->map(fn (Refund $refund) => ['reference' => $refund->refund_number, 'amount' => $this->money($refund->amount), 'provider' => $refund->payment_provider]), 'gift_wrapping' => ['orders' => $orders->where('gift_wrapping', true)->count(), 'income' => $this->money($orders->sum('gift_wrapping_fee'))]];
    }

    private function applyLedgerFilters(Builder $query, array $filters): void
    {
        if (filled($filters['account_id'] ?? null)) $query->where('account_id', $filters['account_id']);
        if (filled($filters['account_type'] ?? null)) $query->whereHas('account', fn (Builder $account) => $account->where('type', $filters['account_type']));
        if (filled($filters['reference'] ?? null)) $query->whereHas('journalEntry', fn (Builder $entry) => $entry->where('reference', 'like', '%'.$filters['reference'].'%')->orWhere('entry_number', 'like', '%'.$filters['reference'].'%'));
        if (filled($filters['source_type'] ?? null)) $query->whereHas('journalEntry', fn (Builder $entry) => $entry->where('source_type', $filters['source_type']));
        if (($filters['movement'] ?? null) === 'debit') $query->where('debit', '>', 0);
        if (($filters['movement'] ?? null) === 'credit') $query->where('credit', '>', 0);
        if (filled($filters['order_id'] ?? null)) $query->where('order_id', $filters['order_id']);
    }

    private function ledgerOpeningBalances(array $period, array $filters, Collection $accounts): array
    {
        $query = JournalEntryLine::query()->with('account')->whereHas('journalEntry', fn (Builder $entry) => $entry->posted()->where('transaction_date', '<', $period['start']));
        $this->applyLedgerFilters($query, $filters); $lines = $query->get();
        return $accounts->mapWithKeys(function (AccountingAccount $account) use ($lines): array { $movement = $lines->where('account_id', $account->id)->sum(fn (JournalEntryLine $line) => $account->isDebitNormal() ? $this->money($line->debit) - $this->money($line->credit) : $this->money($line->credit) - $this->money($line->debit)); return [$account->id => $this->money($account->opening_balance) + $movement]; })->all();
    }

    private function accountActivity(array $period, Collection $accounts): array { $lines = JournalEntryLine::query()->whereIn('account_id', $accounts->pluck('id'))->whereHas('journalEntry', fn (Builder $entry) => $entry->posted()->whereBetween('transaction_date', [$period['start'], $period['end']]))->get(); return $accounts->mapWithKeys(fn (AccountingAccount $account) => [$account->id => $lines->where('account_id', $account->id)->sum(fn (JournalEntryLine $line) => $account->isDebitNormal() ? $this->money($line->debit) - $this->money($line->credit) : $this->money($line->credit) - $this->money($line->debit))])->all(); }
    private function accountActivityBefore(array $period, Collection $accounts): array { $lines = JournalEntryLine::query()->whereIn('account_id', $accounts->pluck('id'))->whereHas('journalEntry', fn (Builder $entry) => $entry->posted()->where('transaction_date', '<', $period['start']))->get(); return $accounts->mapWithKeys(fn (AccountingAccount $account) => [$account->id => $this->money($account->opening_balance) + $lines->where('account_id', $account->id)->sum(fn (JournalEntryLine $line) => $this->money($line->debit) - $this->money($line->credit))])->all(); }
    private function cashMovementForSources(Collection $lines, string $source, string $side): int { return $lines->filter(fn (JournalEntryLine $line) => $line->journalEntry?->source_type === $source)->sum(fn (JournalEntryLine $line) => $this->money($line->{$side})); }
    private function cashMovementForOwner(Collection $lines, string $type, string $side): int { return $lines->filter(fn (JournalEntryLine $line) => $line->ownerTransaction?->transaction_type === $type)->sum(fn (JournalEntryLine $line) => $this->money($line->{$side})); }
    private function orderCost(Collection $orderIds): int { return $orderIds->isEmpty() ? 0 : $this->money(DB::table('order_items')->whereIn('order_id', $orderIds)->selectRaw('COALESCE(SUM(COALESCE(unit_cost, 0) * quantity), 0) as cost')->value('cost')); }
    private function within(Builder $query, array $period, string $column = 'created_at'): Builder { return $query->whereBetween($column, [$period['start'], $period['end']]); }
    private function dates(array $period): Collection { $dates = collect(); for ($date = $period['start']->startOfDay(); $date->lessThanOrEqualTo($period['end']->startOfDay()); $date = $date->addDay()) $dates->push($date); return $dates; }
    private function money(mixed $amount): int { $value = trim((string) ($amount ?? '0')); if ($value === '') return 0; if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) return 0; $cents = ((int) $m[2] * 100) + (int) str_pad($m[3] ?? '', 2, '0'); return $m[1] === '-' ? -$cents : $cents; }
}
