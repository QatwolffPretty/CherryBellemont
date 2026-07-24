@php($brand = config('store.brand'))
@component('emails.layouts.transactional', ['preheader' => 'Return '.($return->return_number)])
    <p style="margin:0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Aftercare</p>
    <h1 style="margin:12px 0 0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.25;">{{ $event === 'new_request' ? 'New return request' : 'Return action required' }}</h1>
    <p style="margin:20px 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">{{ $return->return_number }} · Order {{ $order?->order_number ?? '—' }}<br>{{ $return->customer_name }} · {{ $return->customer_email }}<br>Status: {{ str($return->status)->replace('_', ' ')->title() }}</p>
    @component('emails.components.primary-button', ['url' => $actionUrl])Review return@endcomponent
@endcomponent
