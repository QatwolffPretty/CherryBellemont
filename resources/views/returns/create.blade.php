@inject('settings', '\\App\\Services\\SettingsService')
<x-layouts.store :title="'Return request | Cherry Bellemont'">
    <section class="mx-auto max-w-5xl px-6 py-16">
        <p class="uppercase tracking-[.25em] text-gold">Aftercare</p>
        <h1 class="mt-3 text-4xl">Request a return, exchange or refund</h1>
        <p class="mt-4 max-w-3xl text-cream/70">Order {{ $order->order_number }} is eligible for review. Select the items and tell us what happened. We will review your request before giving any return instructions.</p>

        @if($errors->any())
            <div class="mt-6 border border-gold/50 p-4 text-gold">
                <p class="font-semibold">Please review the highlighted information.</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form class="mt-8 space-y-8" method="POST" enctype="multipart/form-data" action="{{ $token ? route('returns.guest.store', ['order' => $order->order_number, 'token' => $token]) : route('returns.store', ['order' => $order]) }}">
            @csrf
            <section class="border border-cream/15 p-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <label>Request type
                        <select class="field mt-2" name="request_type" required>
                            <option value="return" @selected(old('request_type') === 'return')>Return for review</option>
                            <option value="refund" @selected(old('request_type') === 'refund')>Refund request</option>
                            <option value="exchange" @selected(old('request_type') === 'exchange')>Exchange request</option>
                        </select>
                    </label>
                    <label>Preferred outcome (optional)
                        <select class="field mt-2" name="preferred_resolution">
                            <option value="">Please select</option>
                            <option value="refund" @selected(old('preferred_resolution') === 'refund')>Refund</option>
                            <option value="exchange" @selected(old('preferred_resolution') === 'exchange')>Exchange</option>
                            <option value="replacement" @selected(old('preferred_resolution') === 'replacement')>Replacement review</option>
                            <option value="store_review_required" @selected(old('preferred_resolution') === 'store_review_required')>Please advise</option>
                        </select>
                    </label>
                </div>
                <label class="mt-6 block">Additional details (optional)
                    <textarea class="field mt-2 min-h-28" name="customer_details" maxlength="3000" placeholder="Describe the issue, including any details that will help us review your request.">{{ old('customer_details') }}</textarea>
                </label>
            </section>

            <section class="border border-cream/15 p-6">
                <h2 class="text-2xl">Items to return</h2>
                <div class="mt-5 space-y-5">
                    @foreach($items as $item)
                        <div class="grid gap-4 border-b border-cream/15 pb-5 md:grid-cols-[1fr_8rem_13rem]">
                            <div>
                                <p>{{ $item->product_name ?? $item->name }}</p>
                                <p class="mt-1 text-sm text-cream/60">Purchased: {{ $item->quantity }} · Available to request: {{ $item->returnable_quantity }}</p>
                                <input type="hidden" name="items[{{ $item->id }}][order_item_id]" value="{{ $item->id }}">
                            </div>
                            <label>Quantity<input class="field mt-2" type="number" min="1" max="{{ $item->returnable_quantity }}" name="items[{{ $item->id }}][quantity]" value="{{ old('items.'.$item->id.'.quantity', 1) }}" required></label>
                            <label>Reason
                                <select class="field mt-2" name="items[{{ $item->id }}][reason]" required>
                                    @foreach(['damaged_item' => 'Damaged item', 'defective_item' => 'Defective item', 'incorrect_item' => 'Incorrect item', 'missing_item' => 'Missing item', 'materially_different' => 'Materially different', 'size_or_suitability' => 'Size or suitability', 'other' => 'Other'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('items.'.$item->id.'.reason') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="border border-cream/15 p-6">
                <label>Supporting photos (optional, up to {{ $settings->get('returns.maximum_images', config('store.returns.maximum_return_images', 5)) }})
                    <input class="field mt-2" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                </label>
                <label class="mt-6 flex items-start gap-3 text-sm text-cream/75"><input class="mt-1" type="checkbox" name="policy_acknowledged" value="1" required @checked(old('policy_acknowledged'))><span>I understand that submitting a request does not guarantee a refund, exchange or return approval. I have reviewed the <a class="text-gold underline" href="{{ route('refund.policy') }}">Refund &amp; Returns Policy</a>.</span></label>
            </section>

            <button class="luxury-link" type="submit">Submit return request</button>
        </form>
    </section>
</x-layouts.store>
