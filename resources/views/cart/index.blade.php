<x-layouts.store title="Your bag | Cherry Bellemont">
    <section class="mx-auto max-w-6xl px-6 py-16">
        <div class="flex items-end justify-between">
            <div><p class="uppercase tracking-[.25em] text-gold">Your selection</p><h1 class="mt-3 text-5xl">Your bag</h1></div>
            @if($count)<form method="POST" action="{{ route('cart.clear') }}">@csrf @method('DELETE')<button class="nav-link" type="submit">Clear bag</button></form>@endif
        </div>

        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif
        @error('cart')<p class="mt-6 border border-gold/50 p-4 text-gold">{{ $message }}</p>@enderror
        @error('coupon')<p class="mt-6 border border-gold/50 p-4 text-gold">{{ $message }}</p>@enderror
        @if($couponMessage)<p class="mt-6 border border-gold/50 p-4 text-gold">{{ $couponMessage }}</p>@endif

        @if($lines->isEmpty())
            <div class="mt-10 border border-cream/15 p-12 text-center"><p class="text-xl text-cream/70">Your bag is waiting for its first piece.</p><a class="luxury-link mt-6 inline-block" href="{{ route('collection') }}">Explore collection</a></div>
        @else
            <div class="mt-10 space-y-5">
                @foreach($lines as $line)
                    <article class="grid gap-5 border border-cream/15 p-5 sm:grid-cols-[7rem_1fr_auto]">
                        <div class="aspect-[3/4] overflow-hidden bg-wine-deep">
                            @if($line['image_path'])<img class="h-full w-full object-cover" src="{{ asset('storage/'.$line['image_path']) }}" alt="{{ $line['product']->name }}">@else<div class="flex h-full items-center justify-center text-gold/70">CB</div>@endif
                        </div>
                        <div>
                            <h2 class="text-2xl">{{ $line['product']->name }}</h2>
                            <p class="mt-1 text-gold">RM {{ number_format($line['unit_price'] / 100, 2) }}</p>
                            @if($line['variant_description'])<p class="mt-2 text-sm text-cream/70">@if($line['colour_name'])Colour: {{ $line['colour_name'] }}@endif @if($line['colour_name'] && $line['size_name'])<span aria-hidden="true">&middot;</span>@endif @if($line['size_name'])Size: {{ $line['size_name'] }}@endif</p>@endif
                            @if($line['sku'])<p class="mt-1 text-xs uppercase tracking-[.12em] text-cream/50">SKU: {{ $line['sku'] }}</p>@endif
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('cart.update', $line['product']) }}">@csrf @method('PATCH')<input type="hidden" name="cart_key" value="{{ $line['key'] }}"><input type="hidden" name="quantity" value="{{ max(1, $line['quantity'] - 1) }}"><button class="nav-link" type="submit" @disabled($line['quantity'] <= 1)>-</button></form>
                                <form class="flex items-center gap-2" method="POST" action="{{ route('cart.update', $line['product']) }}">@csrf @method('PATCH')<input type="hidden" name="cart_key" value="{{ $line['key'] }}"><input class="field w-20" type="number" name="quantity" min="1" max="{{ $line['available_stock'] }}" value="{{ $line['quantity'] }}"><button class="nav-link" type="submit">Update</button></form>
                                <form method="POST" action="{{ route('cart.update', $line['product']) }}">@csrf @method('PATCH')<input type="hidden" name="cart_key" value="{{ $line['key'] }}"><input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}"><button class="nav-link" type="submit" @disabled($line['quantity'] >= $line['available_stock'])>+</button></form>
                            </div>
                            @error('quantity')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex flex-col items-end justify-between"><p class="text-xl text-gold">RM {{ number_format($line['line_total'] / 100, 2) }}</p><form method="POST" action="{{ route('cart.destroy', $line['product']) }}">@csrf @method('DELETE')<input type="hidden" name="cart_key" value="{{ $line['key'] }}"><button class="nav-link" type="submit">Remove</button></form></div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10 ml-auto max-w-sm border-t border-gold/40 pt-6">
                <p class="mb-3 uppercase tracking-[.16em] text-gold">Coupon</p>
                @if($couponSummary['coupon'])
                    <div class="flex items-center justify-between border border-gold/40 p-4"><span>{{ $couponSummary['coupon_code'] }}</span><form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE')<button class="nav-link" type="submit">Remove</button></form></div>
                @else
                    <form class="flex gap-2" method="POST" action="{{ route('cart.coupon.apply') }}">@csrf <input class="field min-w-0" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="Enter coupon code" aria-label="Coupon code"><button class="luxury-link shrink-0 px-4" type="submit">Apply</button></form>
                @endif
                <p class="mt-6 flex justify-between"><span>Subtotal ({{ $count }} items)</span><span>RM {{ number_format($subtotal / 100, 2) }}</span></p>
                <p class="mt-3 flex justify-between"><span>Discount</span><span class="text-gold">&minus;RM {{ number_format($couponSummary['discount_cents'] / 100, 2) }}</span></p>
                <p class="mt-3 flex justify-between"><span>Shipping</span><span class="text-cream/60">Calculated at checkout</span></p>
                <p class="mt-4 flex justify-between text-2xl text-gold"><span>Total before shipping</span><span>RM {{ number_format($couponSummary['total_cents'] / 100, 2) }}</span></p>
                <p class="mt-3 text-sm text-cream/60">Delivery and any free-shipping offer are calculated securely at checkout.</p>
                <a class="luxury-link mt-7 block text-center" href="{{ route('checkout.create') }}">Proceed to checkout</a>
            </div>
        @endif
    </section>
</x-layouts.store>
