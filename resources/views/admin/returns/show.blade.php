<x-layouts.admin :title="$returnRequest->return_number.' | Returns'">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Aftercare request" :title="$returnRequest->return_number" :subtitle="'Order '.($returnRequest->order?->order_number ?? '—').' · '.str($returnRequest->request_type)->replace('_', ' ')->title()">
            <x-slot:breadcrumb><x-admin.button variant="outline" :href="route('admin.returns.index')">Back to returns</x-admin.button></x-slot:breadcrumb>
        </x-admin.page-header>
        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        @if($errors->any())<div class="mt-6 border border-gold/40 p-4 text-gold"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_24rem]">
            <div class="space-y-6">
                <x-admin.card title="Customer & order">
                    <p class="mt-4">{{ $returnRequest->customer_name }}<br>{{ $returnRequest->customer_email }}<br>{{ $returnRequest->order?->customer_phone ?: '—' }}</p>
                    <p class="mt-4">Payment: <x-admin.badge :status="$returnRequest->order?->payment_status" /> <span class="ml-3">Fulfilment: <x-admin.badge :status="$returnRequest->order?->order_status" /></span></p>
                    <p class="mt-4">Payment provider: {{ $returnRequest->order?->payment_provider ?? $returnRequest->order?->payment_method ?? '—' }}</p>
                    <p class="mt-2">Order total: RM {{ number_format((float) ($returnRequest->order?->total ?? 0), 2) }}</p>
                    @if($returnRequest->order?->pickup_location)<p class="mt-4">Pickup: {{ $returnRequest->order->pickup_location }}</p>@else<p class="mt-4">{{ $returnRequest->order?->address_line_1 }} {{ $returnRequest->order?->address_line_2 }}<br>{{ $returnRequest->order?->city }}, {{ $returnRequest->order?->state }} {{ $returnRequest->order?->postcode }}</p>@endif
                </x-admin.card>

                <x-admin.card title="Requested items">
                    @foreach($returnRequest->items as $item)
                        <div class="mt-5 grid gap-4 border-b border-cream/15 pb-5 md:grid-cols-[5rem_1fr_auto]">
                            @if($item->product?->image_path)<img class="h-20 w-16 object-cover" src="{{ asset('storage/'.$item->product->image_path) }}" alt="{{ $item->product_name }}">@else<div class="h-20 w-16 border border-cream/15"></div>@endif
                            <div><p>{{ $item->product_name }}</p><p class="mt-1 text-sm text-cream/65">Requested: {{ $item->requested_quantity }} · Approved: {{ $item->approved_quantity ?? '—' }}</p><p class="mt-1 text-sm text-cream/65">Reason: {{ str($item->reason)->replace('_', ' ')->title() }}</p>@if($item->inspection_notes)<p class="mt-2 text-sm text-cream/75">Inspection: {{ $item->inspection_notes }}</p>@endif</div>
                            <p class="text-gold">RM {{ number_format((float) $item->line_paid_amount, 2) }}</p>
                        </div>
                    @endforeach
                </x-admin.card>

                @if($returnRequest->images->isNotEmpty())
                    <x-admin.card title="Customer evidence">
                        <div class="mt-4 flex flex-wrap gap-3">@foreach($returnRequest->images as $image)<x-admin.button variant="outline" :href="route('admin.returns.images.download', $image)" icon="bi-file-earmark-arrow-down">Evidence {{ $loop->iteration }}</x-admin.button>@endforeach</div>
                    </x-admin.card>
                @endif

                <x-admin.card title="Timeline">
                    <ol class="mt-4 space-y-3 border-l border-gold/50 pl-5">@foreach($returnRequest->events as $event)<li><span class="text-gold">{{ str($event->event_type)->replace('_', ' ')->title() }}</span><span class="ml-2 text-sm text-cream/60">{{ $event->created_at?->format('d M Y H:i') }}</span>@if($event->note)<p class="mt-1 text-sm text-cream/75">{{ $event->note }}</p>@endif</li>@endforeach</ol>
                </x-admin.card>
            </div>

            <div class="space-y-6">
                <x-admin.card title="Request status">
                    <p><x-admin.badge :status="$returnRequest->status" /></p>
                    @if($returnRequest->admin_decision_reason)<p class="mt-4 text-sm text-cream/70">Decision: {{ $returnRequest->admin_decision_reason }}</p>@endif
                    @if($returnRequest->return_instructions)<p class="mt-4 text-sm text-cream/70">Instructions: {{ $returnRequest->return_instructions }}</p>@endif
                </x-admin.card>

                @if($returnRequest->status === 'requested')
                    <x-admin.card title="Review"><form method="POST" action="{{ route('admin.returns.review', $returnRequest) }}">@csrf @method('PATCH')<x-admin.button type="submit">Begin review</x-admin.button></form></x-admin.card>
                @elseif($returnRequest->status === 'under_review')
                    <x-admin.card title="Review decision">
                        <form class="space-y-4" method="POST" action="{{ route('admin.returns.approve', $returnRequest) }}">@csrf @method('PATCH')
                            @foreach($returnRequest->items as $item)<x-admin.form-input :name="'items['.$item->id.'][approved_quantity]'" :label="$item->product_name.' approved quantity'" type="number" min="1" :max="$item->requested_quantity" :value="$item->requested_quantity" />@endforeach
                            <x-admin.textarea name="reason" label="Approval note (optional)" />
                            <x-admin.button type="submit" variant="success">Approve request</x-admin.button>
                        </form>
                        <form class="mt-5 space-y-4 border-t border-cream/15 pt-5" method="POST" action="{{ route('admin.returns.reject', $returnRequest) }}">@csrf @method('PATCH')<x-admin.textarea name="reason" label="Rejection reason" required /><x-admin.button type="submit" variant="danger">Reject request</x-admin.button></form>
                    </x-admin.card>
                @elseif($returnRequest->status === 'approved')
                    <x-admin.card title="Return instructions"><form class="space-y-4" method="POST" action="{{ route('admin.returns.instructions', $returnRequest) }}">@csrf @method('PATCH')<x-admin.textarea name="return_instructions" label="Instructions for the customer" required /><x-admin.button type="submit">Send instructions</x-admin.button></form></x-admin.card>
                @elseif($returnRequest->status === 'awaiting_return')
                    <x-admin.card title="Item receipt"><form method="POST" action="{{ route('admin.returns.received', $returnRequest) }}">@csrf @method('PATCH')<x-admin.button type="submit">Mark item received</x-admin.button></form></x-admin.card>
                @elseif($returnRequest->status === 'item_received')
                    <x-admin.card title="Inspection"><form method="POST" action="{{ route('admin.returns.inspect', $returnRequest) }}">@csrf @method('PATCH')<x-admin.button type="submit">Start inspection</x-admin.button></form></x-admin.card>
                @elseif($returnRequest->status === 'inspecting')
                    <x-admin.card title="Inspection result">
                        <form class="space-y-4" method="POST" action="{{ route('admin.returns.finish-inspection', $returnRequest) }}">@csrf @method('PATCH')
                            @foreach($returnRequest->items as $item)
                                <div class="border-b border-cream/15 pb-4"><p class="mb-3">{{ $item->product_name }}</p><x-admin.form-input :name="'items['.$item->id.'][condition_received]'" label="Condition" /><x-admin.textarea :name="'items['.$item->id.'][inspection_notes]'" label="Inspection notes" /><x-admin.select :name="'items['.$item->id.'][stock_disposition]'" label="Stock disposition"><option value="restocked">Restocked</option><option value="damaged">Damaged</option><option value="written_off">Written off</option><option value="returned_to_supplier">Returned to supplier</option><option value="not_returned">Not returned</option></x-admin.select></div>
                            @endforeach
                            <x-admin.textarea name="reason" label="Inspection note" /><input type="hidden" name="passed" value="1"><x-admin.button type="submit" variant="success">Pass inspection</x-admin.button>
                        </form>
                        <form class="mt-5 border-t border-cream/15 pt-5" method="POST" action="{{ route('admin.returns.finish-inspection', $returnRequest) }}">@csrf @method('PATCH')<input type="hidden" name="passed" value="0"><input type="hidden" name="reason" value="Inspection failed."><x-admin.button type="submit" variant="danger">Fail inspection</x-admin.button></form>
                    </x-admin.card>
                @elseif($returnRequest->status === 'resolution_pending')
                    <x-admin.card title="Refund resolution">
                        <form class="space-y-4" method="POST" action="{{ route('admin.returns.refunds.store', $returnRequest) }}">@csrf
                            <x-admin.select name="refund_type" label="Refund type"><option value="partial">Partial refund</option><option value="full">Full refund</option></x-admin.select>
                            <x-admin.form-input name="shipping_refund_amount" label="Shipping refund (optional)" type="number" step="0.01" min="0" value="0" />
                            <x-admin.form-input name="gift_wrap_refund_amount" label="Gift wrapping refund (optional)" type="number" step="0.01" min="0" value="0" />
                            <x-admin.textarea name="reason" label="Refund reason" />
                            <x-admin.button type="submit" variant="warning">Create refund</x-admin.button>
                        </form>
                        <form class="mt-5 space-y-4 border-t border-cream/15 pt-5" method="POST" action="{{ route('admin.returns.exchange', $returnRequest) }}">@csrf @method('PATCH')<x-admin.textarea name="replacement_details" label="Replacement or exchange details" required /><x-admin.button type="submit">Record exchange</x-admin.button></form>
                    </x-admin.card>
                @endif

                @foreach($returnRequest->refunds as $refund)
                    <x-admin.card :title="'Refund '.$refund->refund_number">
                        <p><x-admin.badge :status="$refund->status" /></p><p class="mt-3 text-gold">RM {{ number_format((float) $refund->amount, 2) }}</p><p class="mt-2 capitalize text-sm text-cream/65">{{ $refund->payment_provider }}</p>
                        @if($refund->failure_reason)<p class="mt-3 text-sm text-gold">{{ $refund->failure_reason }}</p>@endif
                        @if($refund->payment_provider === 'duitnow' && $refund->status === 'pending')
                            <form class="mt-4 space-y-4" method="POST" enctype="multipart/form-data" action="{{ route('admin.refunds.manual-confirm', $refund) }}">@csrf @method('PATCH')<x-admin.form-input name="manual_reference" label="Bank transfer reference" required /><label class="block text-sm">Private transfer proof<input class="admin-form-input mt-2" type="file" name="manual_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required></label><x-admin.button type="submit" variant="success">Confirm transfer</x-admin.button></form>
                        @endif
                        @if($refund->manual_proof_path)<x-admin.button class="mt-4" variant="outline" :href="route('admin.refunds.proof.download', $refund)">Download proof</x-admin.button>@endif
                        @if($refund->status === 'succeeded')<x-admin.button class="mt-4" variant="outline" :href="route('admin.refunds.credit-note', $refund)">Download credit note</x-admin.button>@endif
                    </x-admin.card>
                @endforeach
            </div>
        </div>
    </x-admin.section>
</x-layouts.admin>
