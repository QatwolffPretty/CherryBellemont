<x-layouts.admin :title="($coupon->exists ? 'Edit' : 'Add').' coupon | Cherry Bellemont'">
    <x-admin.section width="2xl">
        <x-admin.page-header eyebrow="Promotions" :title="$coupon->exists ? 'Edit coupon' : 'Add coupon'" />

        <x-admin.card class="mt-8">
            <form class="space-y-6" method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
                @csrf
                @if($coupon->exists)@method('PUT')@endif

                <x-admin.form-input name="code" label="Coupon code" :value="$coupon->code" help="Letters, numbers, hyphens, and underscores only. Codes are saved in uppercase." required />
                <x-admin.form-input name="name" label="Name" :value="$coupon->name" required />
                <x-admin.textarea name="description" label="Description" :value="$coupon->description" />
                <x-admin.select name="type" label="Discount type" required>
                    <option value="percentage" @selected(old('type', $coupon->type ?: 'percentage') === 'percentage')>Percentage</option>
                    <option value="fixed_amount" @selected(old('type', $coupon->type) === 'fixed_amount')>Fixed amount (RM)</option>
                </x-admin.select>
                <x-admin.form-input name="value" label="Discount value" type="number" step="0.01" min="0.01" :value="$coupon->value" required />
                <x-admin.form-input name="minimum_order_amount" label="Minimum order amount (RM)" type="number" step="0.01" min="0" :value="$coupon->minimum_order_amount" />
                <x-admin.form-input name="maximum_discount_amount" label="Maximum percentage discount (RM)" type="number" step="0.01" min="0.01" :value="$coupon->maximum_discount_amount" help="Optional. Used only to cap the calculated discount." />
                <x-admin.form-input name="usage_limit" label="Total usage limit" type="number" min="1" :value="$coupon->usage_limit" />
                <x-admin.form-input name="usage_limit_per_email" label="Usage limit per email" type="number" min="1" :value="$coupon->usage_limit_per_email" />
                <x-admin.form-input name="starts_at" label="Start date and time" type="datetime-local" :value="$coupon->starts_at?->format('Y-m-d\\TH:i')" help="Times use the application's UTC timezone. Leave blank to make the coupon available immediately." />
                <x-admin.form-input name="expires_at" label="Expiry date and time" type="datetime-local" :value="$coupon->expires_at?->format('Y-m-d\\TH:i')" help="Times use the application's UTC timezone. Leave blank when the coupon has no expiry." />
                <x-admin.checkbox name="free_shipping" label="Include free shipping" :checked="old('free_shipping', $coupon->free_shipping)" />
                <x-admin.checkbox name="is_active" label="Active" :checked="old('is_active', $coupon->exists ? $coupon->is_active : true)" />
                <x-admin.button type="submit">Save coupon</x-admin.button>
            </form>
        </x-admin.card>
    </x-admin.section>
</x-layouts.admin>
