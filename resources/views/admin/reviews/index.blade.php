<x-layouts.admin title="Reviews | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Client feedback" title="Reviews" subtitle="Moderate verified-purchase reviews before they appear in the collection." />

        @if(session('success'))<p class="mt-6 border border-gold/40 p-4 text-gold">{{ session('success') }}</p>@endif
        @error('review_ids')<p class="mt-6 border border-gold/40 p-4 text-gold">{{ $message }}</p>@enderror

        <form class="mt-8 grid gap-3 md:grid-cols-5" method="GET">
            <x-admin.form-input name="search" :value="request('search')" placeholder="Customer, product, title" aria-label="Search reviews" class="mt-0 md:col-span-2" />
            <x-admin.select name="status" aria-label="Filter status" class="mt-0"><option value="">All statuses</option>@foreach(['pending', 'approved', 'rejected', 'hidden'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</x-admin.select>
            <x-admin.select name="rating" aria-label="Filter star rating" class="mt-0"><option value="">All ratings</option>@foreach([5,4,3,2,1] as $rating)<option value="{{ $rating }}" @selected((int) request('rating') === $rating)>{{ $rating }} stars</option>@endforeach</x-admin.select>
            <x-admin.button type="submit">Filter</x-admin.button>
            <x-admin.checkbox name="with_images" label="With images" :checked="request()->boolean('with_images')" />
        </form>

        <form id="review-bulk-form" class="mt-6 flex flex-wrap items-center gap-3" method="POST" action="{{ route('admin.reviews.bulk') }}">
            @csrf
            @method('PATCH')
            <x-admin.button type="submit" name="status" value="approved" variant="success">Approve selected</x-admin.button>
            <x-admin.button type="submit" name="status" value="rejected" variant="warning">Reject selected</x-admin.button>
        </form>

        <x-admin.table class="mt-6">
            <x-slot:head><tr><th><span class="sr-only">Select</span></th><th>Review</th><th>Product</th><th>Rating</th><th>Status</th><th>Submitted</th><th><span class="sr-only">Actions</span></th></tr></x-slot:head>
            @forelse($reviews as $review)
                <tr>
                    <td><input form="review-bulk-form" class="admin-check" type="checkbox" name="review_ids[]" value="{{ $review->id }}" aria-label="Select review by {{ $review->customer_name }}"></td>
                    <td><p>{{ $review->customer_name }}</p><p class="mt-1 max-w-xs truncate text-sm text-cream/60">{{ $review->title }}</p></td>
                    <td>{{ $review->product?->name ?? 'Removed product' }}</td>
                    <td><span class="text-gold">{{ $review->rating }} ★</span>@if($review->images->isNotEmpty())<i class="bi bi-image ml-2 text-cream/60" aria-label="Includes images"></i>@endif</td>
                    <td><x-admin.badge :status="$review->status" /></td>
                    <td>{{ $review->created_at->format('d M Y') }}</td>
                    <td><x-admin.button variant="outline" :href="route('admin.reviews.show', $review)">View</x-admin.button></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-admin.empty-state title="No reviews found" description="New verified-purchase reviews will appear here for moderation." icon="bi-chat-square-quote" /></td></tr>
            @endforelse
        </x-admin.table>
        <div class="mt-8">{{ $reviews->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
