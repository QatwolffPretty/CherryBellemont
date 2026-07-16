@php
    $brand = config('store.brand');
@endphp
@component('emails.layouts.transactional', ['preheader' => $title])
    <p style="margin:0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Cherry Bellemont Operations</p>
    <h1 style="margin:12px 0 0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.25;">{{ $title }}</h1>
    @include('emails.components.status-badge', ['label' => $status, 'tone' => $statusTone])

    @if($order)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:20px 0;font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Order</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->order_number }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Order date</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ optional($order->created_at)->format('d M Y H:i') }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Customer</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->customer_name }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Email</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->customer_email }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Phone</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->customer_phone }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Payment</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->payment_method === 'stripe' ? 'Stripe' : 'DuitNow' }} · {{ ucfirst($order->payment_status) }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Fulfilment</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ ucfirst($order->order_status) }}</td></tr>
            <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Delivery</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->shipping_method_name ?: 'To be confirmed' }}</td></tr>
            @if($event === 'stripe_payment_confirmed')
                <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Stripe paid</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ optional($order->stripe_paid_at)->format('d M Y H:i') }}</td></tr>
            @endif
            @if($event === 'duitnow_payment_approved')
                <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Approved by</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $reviewerName ?? 'Administrator' }}</td></tr>
                <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Approved at</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ optional($approvedAt ?? null)->format('d M Y H:i') }}</td></tr>
            @endif
            @if($event === 'order_cancelled')
                <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Cancellation reason</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->cancellation_reason }}</td></tr>
                <tr><td style="padding:4px 0;color:{{ $brand['muted_burgundy'] }};">Stock restored</td><td align="right" style="padding:4px 0;color:{{ $brand['dark_wine'] }};">{{ $order->stock_restored_at ? 'Yes' : 'No' }}</td></tr>
            @endif
        </table>
        @include('emails.components.order-summary', ['order' => $order])
    @endif

    @if($receipt)
        <p style="margin:20px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Receipt submitted {{ optional($receipt->submitted_at ?? $receipt->created_at)->format('d M Y H:i') }}. Status: {{ ucfirst($receipt->status) }}. No file is attached to this email.</p>
    @endif

    @if($product)
        <p style="margin:20px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Product: {{ $product->name }}@if($product->sku) · SKU: {{ $product->sku }}@endif<br>Remaining stock: {{ $product->stock }}</p>
    @endif

    @if($review)
        <p style="margin:20px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Product: {{ $review->product?->name }}<br>Customer: {{ $review->customer_name }}<br>Rating: {{ $review->rating }}/5<br>Title: {{ $review->title }}<br>Submitted: {{ optional($review->created_at)->format('d M Y H:i') }}</p>
    @endif

    @if($subscriber)
        <p style="margin:20px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Name: {{ $subscriber->name ?: 'Not supplied' }}<br>Email: {{ $subscriber->email }}<br>Source: {{ $subscriber->source ?: 'Not supplied' }}<br>Subscribed: {{ optional($subscriber->subscribed_at)->format('d M Y H:i') }}</p>
    @endif

    @if($event === 'payment_attention')
        <p style="margin:20px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Provider: {{ $provider ?? 'Stripe' }}<br>Reference: {{ $reference ?? 'Not available' }}<br>Summary: {{ $summary ?? 'A payment-processing event requires review.' }}<br>Occurred: {{ optional($occurredAt ?? now())->format('d M Y H:i') }}</p>
    @endif

    @if($actionUrl)
        @component('emails.components.primary-button', ['url' => $actionUrl])
            {{ $actionLabel }}
        @endcomponent
    @endif
@endcomponent
