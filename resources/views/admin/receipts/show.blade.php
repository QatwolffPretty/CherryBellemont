<x-layouts.admin title="Review payment receipt">
    <x-admin.section>
        <x-admin.page-header :title="$receipt->order->order_number">
            <x-slot:breadcrumb>
                <x-admin.button variant="outline" :href="route('admin.payment-receipts.index')">Receipt queue</x-admin.button>
            </x-slot:breadcrumb>
        </x-admin.page-header>

        <div class="mt-8 grid gap-8 md:grid-cols-2">
            <x-admin.card title="Order summary">
                <p class="mt-4">{{ $receipt->order->customer_name }}</p>
                <p>{{ $receipt->order->customer_email }} &middot; {{ $receipt->order->customer_phone }}</p>
                <p class="mt-5">Subtotal RM {{ number_format($receipt->order->subtotal, 2) }}</p>
                <p>Shipping RM {{ number_format($receipt->order->shipping_fee, 2) }}</p>
                <p class="text-xl text-gold">Total RM {{ number_format($receipt->order->total, 2) }}</p>
                <p class="mt-4">Payment status: <x-admin.badge :status="$receipt->order->payment_status" /></p>
                <div class="mt-6 space-y-2">
                    @foreach($receipt->order->items as $item)
                        <p>{{ $item->product_name ?? $item->name }} &times; {{ $item->quantity }}</p>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="Submitted receipt">
                <p class="mt-4">{{ $receipt->original_filename }}</p>
                <p class="mt-2">Submitted {{ ($receipt->submitted_at ?? $receipt->created_at)->format('d M Y H:i') }}</p>
                <x-admin.button class="mt-5" :href="route('admin.payment-receipts.download', $receipt)">Download receipt</x-admin.button>

                @if($receipt->status === 'pending')
                    <form class="mt-8" method="POST" action="{{ route('admin.payment-receipts.approve', $receipt) }}">
                        @csrf
                        @method('PATCH')
                        <x-admin.button variant="success" type="submit">Approve payment</x-admin.button>
                    </form>
                    <form class="mt-4 space-y-3" method="POST" action="{{ route('admin.payment-receipts.reject', $receipt) }}">
                        @csrf
                        @method('PATCH')
                        <x-admin.textarea name="rejection_reason" label="Rejection reason" required />
                        <x-admin.button variant="danger" type="submit">Reject receipt</x-admin.button>
                    </form>
                @else
                    <p class="mt-8"><x-admin.badge :status="$receipt->status" /> @if($receipt->rejection_reason) <span class="text-cream/75">{{ $receipt->rejection_reason }}</span> @endif</p>
                @endif
            </x-admin.card>
        </div>
    </x-admin.section>
</x-layouts.admin>
