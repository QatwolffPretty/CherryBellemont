<x-layouts.admin title="Atelier | Cherry Bellemont">
    @php($latestReview = \App\Models\Review::query()->latest()->first())
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
            <x-admin.stats-card label="Pending Reviews" :value="\App\Models\Review::where('status', 'pending')->count()" :href="route('admin.reviews.index', ['status' => 'pending'])" accent />
            <x-admin.stats-card label="Approved Reviews" :value="\App\Models\Review::approved()->count()" :href="route('admin.reviews.index', ['status' => 'approved'])" />
            <x-admin.stats-card label="Average Rating" :value="number_format((float) \App\Models\Review::approved()->avg('rating'), 1).' ★'" :href="route('admin.reviews.index', ['status' => 'approved'])" />
            <x-admin.stats-card label="Latest Review" :value="$latestReview ? $latestReview->rating.' ★' : '—'" :subtitle="$latestReview?->title ?? 'No reviews yet'" :href="route('admin.reviews.index')" />
        </div>
    </x-admin.section>
</x-layouts.admin>
