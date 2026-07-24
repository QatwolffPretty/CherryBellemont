{{ $return->customer_name ?: 'Customer' }},

{{ str($event)->replace('_', ' ')->title() }} — {{ $return->return_number }}
Order: {{ $order?->order_number ?? '—' }}
Return status: {{ str($return->status)->replace('_', ' ')->title() }}
@if($return->admin_decision_reason)Reason: {{ $return->admin_decision_reason }}@endif
@if($return->return_instructions)Instructions: {{ $return->return_instructions }}@endif
@if($secureUrl)View your secure return request: {{ $secureUrl }}@endif
