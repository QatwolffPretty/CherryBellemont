<x-layouts.admin title="Coupons | Cherry Bellemont">
    <x-admin.section width="7xl">
        <x-admin.page-header eyebrow="Promotions" title="Coupons" subtitle="Manage secure server-side discounts for the collection.">
            <x-slot:actions><x-admin.button :href="route('admin.coupons.create')" icon="bi-ticket-perforated">Add coupon</x-admin.button></x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif
        @error('coupon')<p class="mt-6 border border-gold/50 p-4 text-gold">{{ $message }}</p>@enderror

        <form class="mt-8 flex flex-wrap gap-3" method="GET" action="{{ route('admin.coupons.index') }}">
            <x-admin.select name="status" aria-label="Filter coupon status" class="mt-0 w-48">
                <option value="">All coupons</option>
                @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'expired' => 'Expired', 'scheduled' => 'Scheduled'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.button type="submit" variant="outline">Filter</x-admin.button>
        </form>

        <x-admin.table class="mt-8">
            <x-slot:head><tr><th>Code</th><th>Offer</th><th>Usage</th><th>Availability</th><th>Expiry</th><th>Status</th><th></th></tr></x-slot:head>
            @forelse($coupons as $coupon)
                <tr>
                    <td><strong class="text-gold">{{ $coupon->code }}</strong><br><span class="text-sm text-cream/60">{{ $coupon->name }}</span></td>
                    <td>{{ $coupon->type === 'percentage' ? number_format($coupon->value, 2).'%' : 'RM '.number_format($coupon->value, 2) }}@if($coupon->free_shipping)<br><span class="text-sm text-gold">Free shipping</span>@endif</td>
                    <td>{{ $coupon->usages_count }}@if($coupon->usage_limit) / {{ $coupon->usage_limit }}@endif</td>
                    <td>@if($coupon->starts_at){{ $coupon->starts_at->format('d M Y H:i') }}@else&mdash;@endif</td>
                    <td>@if($coupon->expires_at){{ $coupon->expires_at->format('d M Y H:i') }}@else&mdash;@endif</td>
                    <td><x-admin.badge :status="$coupon->is_active ? 'active' : 'archived'" :label="$coupon->is_active ? 'Active' : 'Inactive'" /></td>
                    <td class="text-right">
                        <x-admin.button variant="outline" :href="route('admin.coupons.edit', $coupon)">Edit</x-admin.button>
                        @if($coupon->usages_count === 0)
                            <form class="ml-2 inline" method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete this unused coupon?')">
                                @csrf @method('DELETE')
                                <x-admin.button variant="danger" type="submit">Delete</x-admin.button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-admin.empty-state title="No coupons found." description="Create a coupon to offer a considered incentive to your customers." icon="bi-ticket-perforated" /></td></tr>
            @endforelse
        </x-admin.table>

        <div class="mt-8">{{ $coupons->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
