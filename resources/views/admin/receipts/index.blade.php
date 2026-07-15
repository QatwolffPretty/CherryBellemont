<x-layouts.admin title="Payment receipts">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Payment verification" title="DuitNow receipts" />

        <div class="mt-5 flex flex-wrap gap-3">
            <x-admin.button variant="outline" :href="route('admin.payment-receipts.index')">All</x-admin.button>
            <x-admin.button variant="outline" href="?status=pending">Pending</x-admin.button>
            <x-admin.button variant="outline" href="?status=approved">Approved</x-admin.button>
            <x-admin.button variant="outline" href="?status=rejected">Rejected</x-admin.button>
        </div>

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif

        @if($receipts->isEmpty())
            <x-admin.empty-state class="mt-10" title="No payment receipts have been submitted yet." description="A receipt appears here only after a customer uploads one from the DuitNow payment page." icon="bi-credit-card" />
        @else
            <x-admin.table class="mt-8">
                <x-slot:head>
                    <tr><th>Order</th><th>Customer</th><th>Total</th><th>Receipt</th><th>Submitted</th><th>Status</th><th></th></tr>
                </x-slot:head>
                @foreach($receipts as $receipt)
                    <tr>
                        <td>{{ $receipt->order->order_number }}</td>
                        <td>{{ $receipt->order->customer_name }}<br><small>{{ $receipt->order->customer_email }} &middot; {{ $receipt->order->customer_phone }}</small></td>
                        <td>RM {{ number_format($receipt->order->total, 2) }}</td>
                        <td><x-admin.button variant="outline" :href="route('admin.payment-receipts.download', $receipt)">{{ $receipt->original_filename ?: 'Download' }}</x-admin.button></td>
                        <td>{{ ($receipt->submitted_at ?? $receipt->created_at)->format('d M Y H:i') }}</td>
                        <td><x-admin.badge :status="$receipt->status" /></td>
                        <td><x-admin.button variant="outline" :href="route('admin.payment-receipts.show', $receipt)">View</x-admin.button></td>
                    </tr>
                @endforeach
            </x-admin.table>
            <div class="mt-8">{{ $receipts->links() }}</div>
        @endif
    </x-admin.section>
</x-layouts.admin>
