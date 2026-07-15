<x-layouts.store :title="($context['review'] ? 'Edit review' : 'Write a review').' | Cherry Bellemont'">
    @php
        $review = $context['review'];
        $order = $context['order'];
    @endphp

    <section class="mx-auto max-w-3xl px-6 py-16">
        <p class="uppercase tracking-[.25em] text-gold">Verified purchase</p>
        <h1 class="mt-3 text-4xl">{{ $review ? 'Edit your review' : 'Share your experience' }}</h1>
        <p class="mt-4 text-cream/70">Your feedback is linked to order {{ $order->order_number }} and will be published after review.</p>

        @error('review')<p class="mt-6 border border-gold/50 p-4 text-gold">{{ $message }}</p>@enderror

        <form class="mt-8 space-y-6 border border-cream/15 p-6" method="POST" enctype="multipart/form-data" action="{{ $review ? route('reviews.update', ['product' => $product, 'review' => $review]) : route('reviews.store', $product) }}">
            @csrf
            @if($review)@method('PATCH')@endif
            <input type="hidden" name="order_number" value="{{ $order->order_number }}">
            <input type="hidden" name="guest_access_token" value="{{ request('guest_access_token') }}">

            <div>
                <label for="customer_email">Email used for this order</label>
                <input id="customer_email" class="field mt-2" type="email" name="customer_email" value="{{ old('customer_email', $order->customer_email) }}" required>
                @error('customer_email')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="rating">Rating</label>
                <select id="rating" class="field mt-2" name="rating" required>
                    @foreach([5, 4, 3, 2, 1] as $rating)
                        <option value="{{ $rating }}" @selected(old('rating', $review?->rating ?? 5) == $rating)>{{ str_repeat('★', $rating) }}{{ str_repeat('☆', 5 - $rating) }} — {{ $rating }} star{{ $rating === 1 ? '' : 's' }}</option>
                    @endforeach
                </select>
                @error('rating')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="title">Review title</label>
                <input id="title" class="field mt-2" name="title" value="{{ old('title', $review?->title) }}" maxlength="160" required>
                @error('title')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="review">Your review</label>
                <textarea id="review" class="field mt-2 min-h-40" name="review" maxlength="4000" required>{{ old('review', $review?->review) }}</textarea>
                @error('review')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
            </div>

            @if($review && $review->images->isNotEmpty())
                <div>
                    <p>Current photos</p>
                    <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-5">
                        @foreach($review->images as $image)
                            <label class="border border-cream/15 p-2 text-sm">
                                <img class="aspect-square w-full object-cover" src="{{ asset('storage/'.$image->image_path) }}" alt="Current review photo">
                                <span class="mt-2 flex items-center gap-2"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}"> Remove</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <label for="images">Photos (up to 5)</label>
                <input id="images" class="field mt-2" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                <p class="mt-2 text-sm text-cream/60">JPG, PNG or WEBP only. Maximum 5 MB per photo.</p>
                @error('images')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
                @error('images.*')<p class="mt-2 text-gold">{{ $message }}</p>@enderror
                <div id="review-image-preview" class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5" aria-live="polite"></div>
            </div>

            <button class="luxury-link" type="submit">{{ $review ? 'Update review' : 'Submit review' }}</button>
        </form>
    </section>

    <script>
        const imageInput = document.getElementById('images');
        const imagePreview = document.getElementById('review-image-preview');

        imageInput.addEventListener('change', () => {
            imagePreview.replaceChildren();
            [...imageInput.files].slice(0, 5).forEach((file) => {
                const image = document.createElement('img');
                image.className = 'aspect-square w-full border border-cream/15 object-cover';
                image.alt = 'Selected review photo preview';
                image.src = URL.createObjectURL(file);
                imagePreview.append(image);
            });
        });
    </script>
</x-layouts.store>
