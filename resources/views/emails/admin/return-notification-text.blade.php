{{ $event === 'new_request' ? 'New return request' : 'Return action required' }}
{{ $return->return_number }} · Order {{ $order?->order_number ?? '—' }}
{{ $return->customer_name }} · {{ $return->customer_email }}
Status: {{ str($return->status)->replace('_', ' ')->title() }}
Review: {{ $actionUrl }}
