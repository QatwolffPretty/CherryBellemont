<x-layouts.store title="Track Your Order | Cherry Bellemont" robots="noindex, nofollow">
    <section class="mx-auto max-w-xl px-6 py-16 sm:py-24">
        <div class="border border-gold/40 bg-wine-deep/35 p-7 sm:p-10">
            <p class="uppercase tracking-[.25em] text-gold">Guest orders</p>
            <h1 class="mt-3 text-4xl">Track Your Order</h1>
            <p class="mt-5 leading-7 text-cream/75">Enter your order number and the email address used during checkout.</p>

            @if($errors->has('lookup'))
                <p class="mt-6 border border-gold/50 p-4 text-gold" role="alert">{{ $errors->first('lookup') }}</p>
            @endif

            <form class="mt-8 space-y-5" method="POST" action="{{ route('orders.lookup.search') }}">
                @csrf
                <div>
                    <label for="order_number">Order Number</label>
                    <input id="order_number" class="field mt-2" name="order_number" value="{{ old('order_number') }}" autocomplete="off" required>
                    @error('order_number')<p class="mt-2 text-sm text-gold" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email">Email Address</label>
                    <input id="email" class="field mt-2" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')<p class="mt-2 text-sm text-gold" role="alert">{{ $message }}</p>@enderror
                </div>
                <button class="luxury-link w-full" type="submit">Track Order</button>
            </form>

            <p class="mt-6 text-sm leading-6 text-cream/60">You can find your order number in your order confirmation email.</p>
        </div>
    </section>
</x-layouts.store>
