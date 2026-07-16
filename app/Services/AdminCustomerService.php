<?php

namespace App\Services;

use App\Models\CustomerNote;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminCustomerService
{
    /** @return array<string, string> */
    public function filterOptions(): array
    {
        return [
            'all' => 'All customers',
            'registered' => 'Registered customers',
            'guest' => 'Guest customers',
            'newsletter' => 'Newsletter subscribers',
            'paid' => 'Customers with paid orders',
            'no_paid_orders' => 'Customers with no paid orders',
        ];
    }

    /** @param array{search: ?string, filter: string} $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        /** @var Paginator $customers */
        $customers = $this->summaryQuery($filters)->paginate(20)->withQueryString();
        $this->decorate($customers->getCollection());

        return $customers;
    }

    /** @param array{search: ?string, filter: string} $filters */
    public function exportRows(array $filters): Collection
    {
        $customers = $this->summaryQuery($filters)->get();
        $this->decorate($customers);

        return $customers;
    }

    /** @return array<string, mixed> */
    public function detail(string $email): array
    {
        $email = $this->normalizeEmail($email);
        abort_unless($email !== '', 404);

        $summary = $this->summaryQuery(['search' => null, 'filter' => 'all'])
            ->having('customer_email', '=', $email)
            ->first();

        abort_unless($summary, 404);
        $this->decorate(collect([$summary]));

        $orders = $this->ordersForEmail($email)
            ->with('deliveryMethod:id,name')
            ->latest()
            ->paginate(15, ['*'], 'orders_page');
        $allOrders = $this->ordersForEmail($email)->latest()->get();
        $addresses = $allOrders
            ->map(function (Order $order): ?string {
                if ($order->pickup_location) {
                    return 'Self Pickup: '.$order->pickup_location;
                }

                $lines = array_filter([
                    $order->address_line_1,
                    $order->address_line_2,
                    trim(implode(', ', array_filter([$order->city, $order->state]))),
                    trim(implode(' ', array_filter([$order->postcode, $order->country]))),
                ]);

                return $lines === [] ? null : implode("\n", $lines);
            })
            ->filter()
            ->unique()
            ->values();

        return [
            'customer' => $summary,
            'orders' => $orders,
            'addresses' => $addresses,
            'paymentMethods' => $allOrders
                ->map(fn (Order $order): string => $order->payment_provider ?: $order->payment_method ?: 'Unknown')
                ->unique()
                ->values(),
            'coupons' => $allOrders->pluck('coupon_code')->filter()->unique()->values(),
            'notes' => CustomerNote::query()
                ->with('admin:id,name')
                ->where('customer_email', $email)
                ->latest()
                ->get(),
        ];
    }

    private function summaryQuery(array $filters): Builder
    {
        $email = $this->emailExpression();
        $paidCondition = $this->paidCondition();

        $query = Order::query()
            ->whereRaw($email." <> ''")
            ->selectRaw($email.' as customer_email')
            ->selectRaw('MAX(COALESCE(customer_name, full_name)) as customer_name')
            ->selectRaw('MAX(COALESCE(customer_phone, phone)) as customer_phone')
            ->selectRaw('MIN(created_at) as first_order_at')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN {$paidCondition} THEN 1 ELSE 0 END) as paid_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$paidCondition} THEN total ELSE 0 END), 0) as total_spent")
            ->groupByRaw($email);

        if ($search = $filters['search'] ?? null) {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        match ($filters['filter'] ?? 'all') {
            'registered' => $query->whereExists($this->matchingUserQuery()),
            'guest' => $query->whereNotExists($this->matchingUserQuery()),
            'newsletter' => $query->whereExists($this->matchingSubscriberQuery()),
            'paid' => $query->havingRaw("SUM(CASE WHEN {$paidCondition} THEN 1 ELSE 0 END) > 0"),
            'no_paid_orders' => $query->havingRaw("SUM(CASE WHEN {$paidCondition} THEN 1 ELSE 0 END) = 0"),
            default => null,
        };

        return $query->orderByDesc('last_order_at');
    }

    private function ordersForEmail(string $email): Builder
    {
        return Order::query()->whereRaw($this->emailExpression().' = ?', [$email]);
    }

    private function matchingUserQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('users')->selectRaw('1')->whereRaw('LOWER(TRIM(users.email)) = '.$this->emailExpression());
    }

    private function matchingSubscriberQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('newsletter_subscribers')
            ->selectRaw('1')
            ->where('newsletter_subscribers.status', 'subscribed')
            ->whereRaw('LOWER(TRIM(newsletter_subscribers.email)) = '.$this->emailExpression());
    }

    private function emailExpression(): string
    {
        return 'LOWER(TRIM(COALESCE(customer_email, email)))';
    }

    private function paidCondition(): string
    {
        return "payment_status = 'paid' AND (order_status IS NULL OR order_status != 'cancelled')";
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function decorate(Collection $customers): void
    {
        $emails = $customers->pluck('customer_email')->filter()->values();
        if ($emails->isEmpty()) {
            return;
        }

        $users = User::query()
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails)
            ->get(['id', 'name', 'email'])
            ->keyBy(fn (User $user): string => $this->normalizeEmail($user->email));
        $subscribers = NewsletterSubscriber::query()
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails)
            ->get(['email', 'status'])
            ->keyBy(fn (NewsletterSubscriber $subscriber): string => $this->normalizeEmail($subscriber->email));

        $customers->transform(function (object $customer) use ($users, $subscribers): object {
            $customer->customer_email = $this->normalizeEmail((string) $customer->customer_email);
            $customer->user = $users->get($customer->customer_email);
            $customer->registered = $customer->user !== null;
            $customer->newsletter_status = $subscribers->get($customer->customer_email)?->status ?? 'not subscribed';
            $customer->total_orders = (int) $customer->total_orders;
            $customer->paid_orders = (int) $customer->paid_orders;
            $customer->total_spent = (float) $customer->total_spent;
            $customer->average_order_value = $customer->paid_orders > 0
                ? $customer->total_spent / $customer->paid_orders
                : 0.0;

            return $customer;
        });
    }
}
