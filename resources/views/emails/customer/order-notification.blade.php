@php
    $brand = config('store.brand');
    $heading = match ($event) {
        'order_placed' => 'Order received',
        'receipt_submitted' => 'Receipt received',
        'payment_approved' => $order->payment_method === 'stripe' ? 'Payment confirmed' : 'Payment approved',
        'receipt_rejected' => 'Receipt action required',
        'status_updated' => match ($order->order_status) {
            'processing' => 'We’re preparing your order',
            'packed' => 'Your order has been packed',
            'shipped' => 'Your order is on the way',
            'delivered' => 'Your order has arrived',
            'cancelled' => 'Your order has been cancelled',
            default => 'Order update',
        },
        default => 'Order update',
    };
    $status = match ($event) {
        'payment_approved' => 'Payment paid',
        'receipt_rejected' => 'Payment pending',
        'receipt_submitted' => 'Receipt pending review',
        'status_updated' => 'Order '.ucfirst($order->order_status),
        default => 'Payment '.ucfirst($order->payment_status),
    };
    $statusTone = match ($event) {
        'payment_approved' => 'success',
        'status_updated' => in_array($order->order_status, ['shipped', 'delivered'], true) ? 'shipped' : 'pending',
        default => 'pending',
    };
@endphp
@component('emails.layouts.transactional', ['preheader' => $heading.' — '.$order->order_number])
    <p style="margin:0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Order {{ $order->order_number }}</p>
    <h1 style="margin:12px 0 0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.25;">{{ $heading }}</h1>
    @include('emails.components.customer-greeting', ['name' => $order->customer_name ?: 'Customer'])
    @include('emails.components.status-badge', ['label' => $status, 'tone' => $statusTone])

    @if($event === 'order_placed')
        <p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Placed on {{ optional($order->created_at)->format('d M Y') }}. Delivery method: {{ $order->shipping_method_name ?: 'To be confirmed' }}.</p>
        @if($order->payment_method === 'duitnow')
            <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Payment method: DuitNow. Payment status: Pending. Please scan the DuitNow QR code and upload your receipt to complete verification.</p>
        @else
            <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Payment method: Card Payment by Stripe. Your payment is pending and will be confirmed only after Stripe’s verified webhook confirms it.</p>
        @endif
    @elseif($event === 'receipt_submitted')
        <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Payment method: DuitNow. Your receipt is pending review, and payment is not confirmed until an administrator approves it.</p>
    @elseif($event === 'payment_approved')
        <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Payment method: {{ $order->payment_method === 'stripe' ? 'Stripe' : 'DuitNow' }}. Payment of RM {{ number_format($order->total, 2) }} is paid. Current fulfilment: {{ ucfirst($order->order_status ?: 'pending') }}
            @if($order->payment_method === 'stripe' && $order->stripe_paid_at)
                , confirmed on {{ $order->stripe_paid_at->format('d M Y H:i') }}
            @endif.
        </p>
    @elseif($event === 'receipt_rejected')
        <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Payment status remains Pending. Reason: {{ $context['reason'] ?? 'Please upload a replacement receipt.' }} Please upload a clear replacement receipt from your secure order page.</p>
    @elseif($event === 'status_updated')
        <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Your order is now {{ ucfirst($order->order_status) }}.</p>
        @if($order->order_status === 'shipped')
            <p style="margin:14px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Courier: {{ $order->courier_name ?: 'To be confirmed' }}. Tracking: {{ $order->tracking_number ?: 'To be confirmed' }}. Shipped on {{ optional($order->shipped_at)->format('d M Y') ?: 'To be confirmed' }}. Delivery method: {{ $order->shipping_method_name ?: 'To be confirmed' }}.</p>
        @endif
        @if($order->order_status === 'packed')
            <p style="margin:14px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Shipment preparation is complete. Delivery method: {{ $order->shipping_method_name ?: 'To be confirmed' }}.</p>
        @endif
        @if($order->order_status === 'delivered')
            <p style="margin:14px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Thank you for choosing Cherry Bellemont. Delivered on {{ optional($order->delivered_at)->format('d M Y') }}.</p>
        @endif
        @if($order->order_status === 'cancelled')
            <p style="margin:14px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Payment status: {{ ucfirst($order->payment_status ?: 'pending') }}. Reason: {{ $order->cancellation_reason ?: 'Please contact support for further information.' }}. For further assistance, contact {{ config('store.support_email') }}. Refund information is provided only where a confirmed refund is recorded.</p>
        @endif
    @endif

    @include('emails.components.divider')
    @include('emails.components.order-summary', ['order' => $order])

    @if($order->gift_wrapping && in_array($event, ['order_placed', 'payment_approved'], true))
        <p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Your Signature Gift Experience is included with this order.{{ $order->gift_message ? ' Gift message: '.$order->gift_message : '' }}</p>
    @endif
    @if($order->gift_wrapping && $event === 'status_updated' && $order->order_status === 'packed')
        <p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Your Signature Gift Experience has been prepared with the order.</p>
    @endif

    @if($event === 'order_placed' && $order->payment_method === 'duitnow' && $duitNowUrl)
        @component('emails.components.primary-button', ['url' => $duitNowUrl])
            Complete DuitNow Payment
        @endcomponent
        @component('emails.components.secondary-button', ['url' => $secureUrl])
            View your secure order
        @endcomponent
    @elseif($event === 'receipt_rejected' && $secureUrl)
        @component('emails.components.primary-button', ['url' => $secureUrl])
            Replace Receipt
        @endcomponent
    @elseif($secureUrl)
        @component('emails.components.primary-button', ['url' => $secureUrl])
            View your secure order
        @endcomponent
        @if($event === 'status_updated' && $order->order_status === 'shipped' && ! empty($context['tracking_url']))
            @component('emails.components.secondary-button', ['url' => $context['tracking_url']])
                Track Shipment
            @endcomponent
        @endif
        @if($event === 'status_updated' && $order->order_status === 'delivered' && $reviewUrl)
            @component('emails.components.secondary-button', ['url' => $reviewUrl])
                Review your purchase
            @endcomponent
        @endif
    @endif
@endcomponent
