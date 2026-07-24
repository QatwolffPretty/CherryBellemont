@php
    $brand = config('store.brand');
    $heading = match ($event) {
        'requested' => 'Return request received', 'approved' => 'Your request has been approved', 'rejected' => 'Return request update',
        'instructions' => 'Your return instructions', 'item_received' => 'Your return has been received', 'inspection_failed' => 'Inspection update',
        'refund_processing' => 'Your refund is being processed', 'refund_succeeded' => 'Your refund has been confirmed',
        'refund_failed' => 'Refund processing update', 'exchange_approved' => 'Your exchange has been approved', 'closed' => 'Your request has been closed',
        default => 'Return request update',
    };
    $latestRefund = $return->refunds->sortByDesc('created_at')->first();
@endphp
@component('emails.layouts.transactional', ['preheader' => $heading.' — '.$return->return_number])
    <p style="margin:0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Return {{ $return->return_number }}</p>
    <h1 style="margin:12px 0 0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.25;">{{ $heading }}</h1>
    @include('emails.components.customer-greeting', ['name' => $return->customer_name ?: 'Customer'])
    @include('emails.components.status-badge', ['label' => str($return->status)->replace('_', ' ')->title(), 'tone' => in_array($return->status, ['approved', 'completed'], true) ? 'success' : 'pending'])
    <p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Order {{ $order?->order_number ?? '—' }}. Your request is currently {{ str($return->status)->replace('_', ' ') }}.</p>
    @if($event === 'rejected' && $return->admin_decision_reason)<p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Reason: {{ $return->admin_decision_reason }}</p>@endif
    @if($event === 'instructions' && $return->return_instructions)<p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">{{ $return->return_instructions }}</p>@endif
    @if(in_array($event, ['refund_processing','refund_succeeded','refund_failed'], true) && $latestRefund)<p style="margin:0 0 14px;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Refund {{ $latestRefund->refund_number }}: RM {{ number_format((float) $latestRefund->amount, 2) }}. Status: {{ str($latestRefund->status)->title() }}.</p>@endif
    <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">Refunds are confirmed only after the applicable provider or manual transfer confirmation has completed.</p>
    @if($secureUrl)@component('emails.components.primary-button', ['url' => $secureUrl])View return request@endcomponent@endif
@endcomponent
