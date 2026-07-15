<x-layouts.admin :title="'Review | '.($review->product?->name ?? 'Cherry Bellemont')">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client feedback" title="Review moderation">
            <x-slot:breadcrumb>
                <x-admin.button variant="outline" :href="route('admin.reviews.index')">Back to reviews</x-admin.button>
            </x-slot:breadcrumb>
        </x-admin.page-header>

        @if(session('success'))
            <p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>
        @endif
        @error('admin_reply')
            <p class="mt-6 border border-gold/40 p-4 text-gold">{{ $message }}</p>
        @enderror

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <x-admin.card title="Customer review">
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-gold">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</p>
                            <h2 class="mt-3 text-2xl">{{ $review->title }}</h2>
                        </div>
                        <x-admin.badge :status="$review->status" />
                    </div>
                    <p class="mt-4 whitespace-pre-line leading-7 text-cream/80">{{ $review->review }}</p>
                    <p class="mt-5 text-sm text-cream/60">{{ $review->customer_name }} · {{ $review->customer_email }} · {{ $review->created_at->format('d M Y H:i') }}</p>
                    @if($review->is_verified_purchase)
                        <p class="mt-4 text-gold"><i class="bi bi-check2-circle" aria-hidden="true"></i> Verified Purchase</p>
                    @endif
                    @if($review->images->isNotEmpty())
                        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                            @foreach($review->images as $image)
                                <a href="{{ asset('storage/'.$image->image_path) }}" target="_blank" rel="noopener"><img class="aspect-square w-full border border-cream/15 object-cover" src="{{ asset('storage/'.$image->image_path) }}" alt="Review image"></a>
                            @endforeach
                        </div>
                    @endif
                    @if($review->admin_reply)
                        <div class="mt-6 border-l border-gold pl-5">
                            <p class="uppercase tracking-[.14em] text-gold">Cherry Bellemont — Official Response</p>
                            <p class="mt-3 whitespace-pre-line">{{ $review->admin_reply }}</p>
                        </div>
                    @endif
                </x-admin.card>

                <x-admin.card title="Related order">
                    <p class="mt-4">Order {{ $review->order?->order_number ?? '—' }} · {{ $review->order?->customer_name ?? '—' }}</p>
                    @foreach(($review->order?->items ?? []) as $item)
                        <p class="mt-3 border-t border-cream/15 pt-3">{{ $item->product_name ?? $item->name }} × {{ $item->quantity }}</p>
                    @endforeach
                </x-admin.card>
            </div>

            <div class="space-y-6">
                <x-admin.card title="Moderation">
                    <p class="mt-4 text-sm text-cream/65">Current status</p>
                    <x-admin.badge class="mt-2" :status="$review->status" />
                    <div class="mt-6 grid gap-3">
                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">@csrf @method('PATCH')<x-admin.button class="w-full" type="submit" variant="success">Approve</x-admin.button></form>
                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}">@csrf @method('PATCH')<x-admin.button class="w-full" type="submit" variant="warning">Reject</x-admin.button></form>
                        <form method="POST" action="{{ route('admin.reviews.hide', $review) }}">@csrf @method('PATCH')<x-admin.button class="w-full" type="submit" variant="outline">Hide</x-admin.button></form>
                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review and its images?')">@csrf @method('DELETE')<x-admin.button class="w-full" type="submit" variant="danger">Delete</x-admin.button></form>
                    </div>
                </x-admin.card>

                <x-admin.card title="Official response">
                    @if($review->admin_reply)
                        <p class="mt-4 text-cream/65">An official response has already been published.</p>
                    @else
                        <form class="mt-4 space-y-4" method="POST" action="{{ route('admin.reviews.reply', $review) }}">
                            @csrf
                            @method('PATCH')
                            <x-admin.textarea name="admin_reply" label="Reply as Cherry Bellemont" required />
                            <x-admin.button type="submit">Publish response</x-admin.button>
                        </form>
                    @endif
                </x-admin.card>
            </div>
        </div>
    </x-admin.section>
</x-layouts.admin>
