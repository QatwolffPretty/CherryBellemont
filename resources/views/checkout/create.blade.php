<x-layouts.store title="Checkout | Cherry Bellemont">
    <section class="mx-auto grid max-w-6xl gap-12 px-6 py-16 lg:grid-cols-[1fr_24rem]">
        <div>
            <p class="uppercase tracking-[.25em] text-gold">Guest checkout</p>
            <h1 class="mt-3 text-5xl">Delivery details</h1>

            @error('cart')
                <p class="mt-6 border border-gold/50 p-4 text-gold">{{ $message }}</p>
            @enderror
            @error('stripe')
                <p class="mt-6 border border-gold/50 p-4 text-gold">{{ $message }}</p>
            @enderror

            @if($pendingStripeOrder)
                <div class="mt-6 border border-gold/50 p-5">
                    <p class="text-gold">Your previous Stripe Checkout attempt was not started. Your order is reserved and can be retried without creating another order.</p>
                    <form class="mt-4" method="POST" action="{{ route('stripe.retry', ['order' => $pendingStripeOrder['order'], 'token' => $pendingStripeOrder['token']]) }}">
                        @csrf
                        <button class="luxury-link" type="submit">Retry Card Payment</button>
                    </form>
                </div>
            @endif

            <form id="checkout-form" class="mt-10 space-y-5" method="POST" action="{{ route('checkout.store') }}">
                @csrf

                @foreach(['customer_name' => 'Full name', 'customer_email' => 'Email', 'customer_phone' => 'Phone number'] as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" class="field mt-2" name="{{ $field }}" value="{{ old($field, $field === 'customer_name' ? auth()->user()?->name : auth()->user()?->email) }}" required>
                        @error($field)<p class="mt-1 text-gold">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <div>
                    <label for="delivery_method_id">Delivery method</label>
                    <select id="delivery_method_id" class="field mt-2" name="delivery_method_id" required>
                        @foreach($deliveryMethods as $method)
                            <option value="{{ $method->id }}" data-pickup="{{ $method->is_pickup ? '1' : '0' }}" @selected(old('delivery_method_id', $deliveryMethods->first()->id) == $method->id)>
                                {{ $method->name }}{{ $method->estimated_days ? ' · '.$method->estimated_days.' days' : '' }}{{ $method->is_pickup ? ' · RM0.00' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('delivery_method_id')<p class="mt-1 text-gold">{{ $message }}</p>@enderror
                </div>

                <div id="address-fields" class="space-y-5">
                    @foreach(['address_line_1' => 'Address line 1', 'address_line_2' => 'Address line 2 (optional)', 'city' => 'City or area', 'state' => 'State', 'postcode' => 'Postcode', 'country' => 'Country'] as $field => $label)
                        <div class="address-field">
                            <label for="{{ $field }}">{{ $label }}</label>
                            <input id="{{ $field }}" class="field mt-2" name="{{ $field }}" value="{{ old($field, $field === 'country' ? 'Malaysia' : '') }}" @if($field !== 'address_line_2') required @endif>
                            @error($field)<p class="mt-1 text-gold">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>

                <div>
                    <label for="delivery_instructions">Delivery instructions (optional)</label>
                    <textarea id="delivery_instructions" class="field mt-2" name="delivery_instructions">{{ old('delivery_instructions') }}</textarea>
                    @error('delivery_instructions')<p class="mt-1 text-gold">{{ $message }}</p>@enderror
                </div>

                <div>
                    <p class="mb-2">Payment method</p>
                    <label><input type="radio" name="payment_method" value="duitnow" @checked(old('payment_method', 'duitnow') === 'duitnow')> DuitNow manual payment</label>
                    <label class="ml-4"><input type="radio" name="payment_method" value="stripe" @checked(old('payment_method') === 'stripe')> Card Payment by Stripe</label>
                    @error('payment_method')<p class="mt-1 text-gold">{{ $message }}</p>@enderror
                </div>

                <p id="shipping-message" class="text-sm text-cream/65" aria-live="polite">Enter delivery details to calculate shipping.</p>
                <button id="place-order" class="luxury-link" type="submit" @disabled($pendingStripeOrder)>Place order</button>
            </form>
        </div>

        <aside class="border border-cream/15 p-6">
            <h2 class="text-2xl">Order summary</h2>
            @foreach($lines as $line)
                <div class="mt-5 flex gap-3">
                    <div class="h-16 w-12 shrink-0 overflow-hidden bg-wine-deep">
                        @if($line['product']->image_path)
                            <img class="h-full w-full object-cover" src="{{ asset('storage/' . $line['product']->image_path) }}" alt="{{ $line['product']->name }}">
                        @else
                            <div class="flex h-full items-center justify-center text-xs text-gold/70">CB</div>
                        @endif
                    </div>
                    <div class="flex flex-1 justify-between gap-3"><span>{{ $line['product']->name }} &times; {{ $line['quantity'] }}</span><span class="text-gold">RM {{ number_format($line['line_total'] / 100, 2) }}</span></div>
                </div>
            @endforeach
            <p class="mt-8 flex justify-between border-t border-cream/15 pt-5"><span>Subtotal</span><span>RM {{ number_format($subtotal / 100, 2) }}</span></p>
            <p class="mt-3 flex justify-between"><span>Shipping</span><span id="shipping-fee">&mdash;</span></p>
            <p class="mt-4 flex justify-between text-xl text-gold"><span>Total</span><span id="grand-total">RM {{ number_format($total / 100, 2) }}</span></p>
        </aside>
    </section>

    <script>
        const form = document.getElementById('checkout-form');
        const method = document.getElementById('delivery_method_id');
        const addressFields = document.getElementById('address-fields');
        const message = document.getElementById('shipping-message');
        const fee = document.getElementById('shipping-fee');
        const total = document.getElementById('grand-total');
        const button = document.getElementById('place-order');
        const subtotal = {{ $subtotal / 100 }};

        function isPickup() {
            return method.options[method.selectedIndex]?.dataset.pickup === '1';
        }

        function toggleAddressFields(pickup) {
            addressFields.hidden = pickup;
            addressFields.querySelectorAll('input').forEach((input) => {
                input.disabled = pickup;
                input.required = ! pickup && input.name !== 'address_line_2';
            });
        }

        async function quote() {
            const pickup = isPickup();
            toggleAddressFields(pickup);

            if (! pickup && (! form.state.value || ! form.city.value || ! form.postcode.value)) {
                message.textContent = 'Enter delivery details to calculate shipping.';
                fee.textContent = '—';
                button.disabled = true;
                return;
            }

            message.textContent = 'Calculating shipping…';
            button.disabled = true;

            try {
                const response = await fetch('{{ route('shipping.quote') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        state: form.state.value,
                        city: form.city.value,
                        postcode: form.postcode.value,
                        delivery_method_id: method.value,
                    }),
                });
                const data = await response.json();

                if (! response.ok) {
                    throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Delivery is unavailable.');
                }

                message.textContent = data.display_label + (data.pickup_location ? ' · ' + data.pickup_location : '');
                fee.textContent = 'RM ' + Number(data.shipping_fee).toFixed(2);
                total.textContent = 'RM ' + (subtotal + Number(data.shipping_fee)).toFixed(2);
                button.disabled = false;
            } catch (error) {
                message.textContent = error.message;
                fee.textContent = '—';
            }
        }

        ['state', 'city', 'postcode'].forEach((name) => form[name].addEventListener('input', quote));
        method.addEventListener('change', quote);
        quote();
    </script>
</x-layouts.store>
