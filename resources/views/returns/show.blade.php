<x-layouts.store :title="$returnRequest->return_number.' | Cherry Bellemont'">
    <section class="mx-auto max-w-6xl px-6 py-16">
        <p class="uppercase tracking-[.25em] text-gold">Aftercare request</p>
        <h1 class="mt-3 text-4xl">{{ $returnRequest->return_number }}</h1>
        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Request details</h2>
                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-cream/60">Request type</dt><dd class="mt-1 capitalize">{{ str($returnRequest->request_type)->replace('_', ' ') }}</dd></div>
                        <div><dt class="text-sm text-cream/60">Requested</dt><dd class="mt-1">{{ $returnRequest->requested_at?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="text-sm text-cream/60">Preferred outcome</dt><dd class="mt-1 capitalize">{{ str($returnRequest->preferred_resolution ?: 'Not specified')->replace('_', ' ') }}</dd></div>
                        <div><dt class="text-sm text-cream/60">Reason</dt><dd class="mt-1">{{ $returnRequest->customer_reason ?: 'Not specified' }}</dd></div>
                    </dl>
                    @if($returnRequest->customer_details)<p class="mt-5 border-t border-cream/15 pt-5 text-cream/75">{{ $returnRequest->customer_details }}</p>@endif
                    @if($returnRequest->admin_decision_reason)<p class="mt-5 border border-gold/40 p-4 text-gold">{{ $returnRequest->admin_decision_reason }}</p>@endif
                    @if($returnRequest->return_instructions)<div class="mt-5 border border-gold/40 p-4"><p class="text-gold">Return instructions</p><p class="mt-2 text-cream/80">{{ $returnRequest->return_instructions }}</p></div>@endif
                </section>

                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Items</h2>
                    @foreach($returnRequest->items as $item)
                        <div class="mt-4 flex justify-between gap-4 border-b border-cream/15 pb-4">
                            <div><p>{{ $item->product_name }}</p><p class="mt-1 text-sm text-cream/60">Requested: {{ $item->requested_quantity }}@if($item->approved_quantity !== null) · Approved: {{ $item->approved_quantity }}@endif</p></div>
                            <p class="text-gold">RM {{ number_format((float) $item->line_paid_amount, 2) }}</p>
                        </div>
                    @endforeach
                </section>

                @if($returnRequest->images->isNotEmpty())
                    <section class="border border-cream/15 p-6">
                        <h2 class="text-2xl">Supporting photos</h2>
                        <div class="mt-5 flex flex-wrap gap-4">
                            @foreach($returnRequest->images as $image)
                                <a class="luxury-link" href="{{ $token ? route('returns.guest.images.download', ['order' => $order->order_number, 'token' => $token, 'returnRequest' => $returnRequest, 'image' => $image]) : route('returns.images.download', ['order' => $order, 'returnRequest' => $returnRequest, 'image' => $image]) }}">Download photo {{ $loop->iteration }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="border border-cream/15 p-6">
                    <h2 class="text-2xl">Request timeline</h2>
                    <ol class="mt-5 space-y-3 border-l border-gold/50 pl-5">
                        @foreach($returnRequest->events as $event)
                            <li><span class="text-gold">{{ str($event->event_type)->replace('_', ' ')->title() }}</span><span class="ml-2 text-sm text-cream/60">{{ $event->created_at?->format('d M Y H:i') }}</span>@if($event->note)<p class="mt-1 text-sm text-cream/75">{{ $event->note }}</p>@endif</li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="border border-cream/15 p-6">
                <p class="text-sm text-cream/60">Return status</p><p class="mt-1 capitalize text-gold">{{ str($returnRequest->status)->replace('_', ' ') }}</p>
                <p class="mt-5 text-sm text-cream/60">Order</p><p class="mt-1">{{ $order->order_number }}</p>
                <p class="mt-5 text-sm text-cream/60">Refund status</p><p class="mt-1 capitalize text-gold">{{ $order->refund_status ?: 'not refunded' }}</p>
                @foreach($returnRequest->refunds as $refund)
                    <div class="mt-4 border-t border-cream/15 pt-4"><p>Refund {{ $refund->refund_number }}</p><p class="mt-1 text-gold">RM {{ number_format((float) $refund->amount, 2) }}</p><p class="mt-1 capitalize text-sm text-cream/60">{{ $refund->status }}</p>@if($refund->status === 'succeeded')<a class="luxury-link mt-3 inline-block" href="{{ $token ? route('returns.guest.credit-note', ['order' => $order->order_number, 'token' => $token, 'returnRequest' => $returnRequest, 'refund' => $refund]) : route('returns.credit-note', ['order' => $order, 'returnRequest' => $returnRequest, 'refund' => $refund]) }}">Download credit note</a>@endif</div>
                @endforeach
                @if($token)
                    <a class="luxury-link mt-6 inline-block" href="{{ route('orders.guest.show', ['order' => $order->order_number, 'token' => $token]) }}">View order</a>
                @elseif(auth()->check())
                    <a class="luxury-link mt-6 inline-block" href="{{ route('orders.show', $order) }}">View order</a>
                @endif
            </aside>
        </div>
    </section>
</x-layouts.store>
