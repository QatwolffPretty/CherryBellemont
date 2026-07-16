{{ config('store.company_name') }}

{{ $title }}

Dear {{ $order->customer_name ?: 'Customer' }},

{{ $message }}

Order: {{ $order->order_number }}
Status: {{ $status }}
@include('emails.partials.order-summary-text', ['order' => $order])

{{ $actionLabel }}: {{ $actionUrl }}

Need help? {{ config('store.support_email') }}
