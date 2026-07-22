{{ config('store.company_name') }}

@if($notification->name)Dear {{ $notification->name }},

@endif{{ $product->name }} is currently available again.
Price: RM {{ number_format($product->price, 2) }}

Availability is limited and items are not reserved until checkout is completed.

View product: {{ $productUrl }}
Cancel this notification: {{ $cancelUrl }}

Need help? {{ config('store.support_email') }}
