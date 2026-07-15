<x-layouts.admin title="Atelier | Cherry Bellemont">
    <x-admin.section>
        <x-admin.page-header eyebrow="Atelier" title="Admin area" />

        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-admin.stats-card label="Pending Orders" :value="\App\Models\Order::where('order_status', 'pending')->count()" :href="route('admin.orders.index', ['order_status' => 'pending'])" />
            <x-admin.stats-card label="Pending Payment Reviews" :value="\App\Models\PaymentReceipt::where('status', 'pending')->count()" :href="route('admin.payment-receipts.index', ['status' => 'pending'])" accent />
            <x-admin.stats-card label="Paid Awaiting Processing" :value="\App\Models\Order::where('payment_status', 'paid')->where('order_status', 'pending')->count()" :href="route('admin.orders.index', ['payment_status' => 'paid', 'order_status' => 'pending'])" />
            @foreach([['Processing Orders', 'processing'], ['Shipped Orders', 'shipped'], ['Delivered Orders', 'delivered']] as [$label, $status])
                <x-admin.stats-card :label="$label" :value="\App\Models\Order::where('order_status', $status)->count()" :href="route('admin.orders.index', ['order_status' => $status])" />
            @endforeach
            <x-admin.stats-card label="Low Stock Products" :value="\App\Models\Product::where('stock', '<=', 5)->count()" subtitle="5 or fewer remaining" :href="route('admin.products.index', ['low_stock' => 1])" />
        </div>
    </x-admin.section>
</x-layouts.admin>
