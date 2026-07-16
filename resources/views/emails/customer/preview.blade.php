@php
    $brand = config('store.brand');
@endphp
@component('emails.layouts.transactional', ['preheader' => $preheader])
    <p style="margin:0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Order {{ $order->order_number }}</p>
    <h1 style="margin:12px 0 0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.25;">{{ $title }}</h1>
    @include('emails.components.customer-greeting', ['name' => $order->customer_name])
    <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">{{ $message }}</p>
    @include('emails.components.status-badge', ['label' => $status, 'tone' => $statusTone])
    @include('emails.components.divider')
    @include('emails.components.order-summary', ['order' => $order])
    @component('emails.components.primary-button', ['url' => $actionUrl])
        {{ $actionLabel }}
    @endcomponent
    @component('emails.components.secondary-button', ['url' => 'mailto:'.config('store.support_email')])
        Need Help?
    @endcomponent
@endcomponent
