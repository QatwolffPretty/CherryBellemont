@php
    $heading = match ($event) {
        'order_placed' => 'Order received',
        'receipt_submitted' => 'Receipt received',
        'payment_approved' => 'Payment approved',
        'receipt_rejected' => 'Receipt action required',
        default => 'Order update',
    };
@endphp
<div style="max-width:640px;margin:auto;background:#f5f1e8;color:#5B1E2D;font-family:Georgia,serif;padding:32px">
    <h1 style="margin:0;color:#5B1E2D;letter-spacing:.12em">CHERRY BELLEMONT</h1>
    <hr style="border-color:#C8A96B">
    <h2>{{ $heading }}</h2>

    <p>Dear {{ $order->customer_name }},</p>
    <p>Order <strong>{{ $order->order_number }}</strong></p>

    @if($event === 'order_placed')
        <p>Payment method: {{ strtoupper($order->payment_method) }}. Payment is pending; please upload your DuitNow receipt to complete verification.</p>
    @elseif($event === 'receipt_submitted')
        <p>Your receipt is pending review. Payment is not confirmed until approved.</p>
    @elseif($event === 'payment_approved')
        <p>Your payment of RM {{ number_format($order->total, 2) }} is approved. Current fulfilment: {{ ucfirst($order->order_status) }}.</p>
    @elseif($event === 'receipt_rejected')
        <p>Reason: {{ $context['reason'] ?? 'Please upload a replacement receipt.' }}</p>
    @elseif($event === 'status_updated')
        <p>Your order is now {{ ucfirst($order->order_status) }}.</p>
        @if($order->order_status === 'shipped')
            <p>Courier: {{ $order->courier_name }}. Tracking: {{ $order->tracking_number }}.</p>
        @endif
        @if($order->order_status === 'cancelled')
            <p>Reason: {{ $order->cancellation_reason }}</p>
        @endif
    @endif

    <ul>
        @foreach($order->items as $item)
            <li>{{ $item->product_name ?? $item->name }} &times; {{ $item->quantity }}</li>
        @endforeach
    </ul>

    <p>Subtotal: RM {{ number_format($order->subtotal, 2) }}<br>Shipping: RM {{ number_format($order->shipping_fee, 2) }}<br><strong>Total: RM {{ number_format($order->total, 2) }}</strong></p>
    <p><a style="display:inline-block;padding:12px 18px;background:#5B1E2D;color:#f5f1e8;text-decoration:none" href="{{ $secureUrl }}">View your secure order</a></p>
</div>
