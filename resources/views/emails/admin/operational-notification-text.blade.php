Cherry Bellemont Operations

{{ $title }}
Status: {{ $status }}

@if($order)
Order: {{ $order->order_number }}
Order date: {{ optional($order->created_at)->format('d M Y H:i') }}
Customer: {{ $order->customer_name }}
Email: {{ $order->customer_email }}
Phone: {{ $order->customer_phone }}
Payment: {{ $order->payment_method === 'stripe' ? 'Stripe' : 'DuitNow' }} · {{ ucfirst($order->payment_status) }}
Fulfilment: {{ ucfirst($order->order_status) }}
Delivery: {{ $order->shipping_method_name ?: 'To be confirmed' }}
Subtotal: RM {{ number_format($order->subtotal, 2) }}
@if(($order->discount_amount ?? 0) > 0)
Discount: -RM {{ number_format($order->discount_amount, 2) }}
@endif
Shipping: RM {{ number_format($order->shipping_fee, 2) }}
Total: RM {{ number_format($order->total, 2) }}
@endif

@if($receipt)
Receipt submitted: {{ optional($receipt->submitted_at ?? $receipt->created_at)->format('d M Y H:i') }}
Receipt status: {{ ucfirst($receipt->status) }}
@endif

@if($product)
Product: {{ $product->name }}
@if($product->sku)
SKU: {{ $product->sku }}
@endif
Remaining stock: {{ $product->stock }}
@endif

@if($review)
Product: {{ $review->product?->name }}
Customer: {{ $review->customer_name }}
Rating: {{ $review->rating }}/5
Title: {{ $review->title }}
@endif

@if($subscriber)
Subscriber: {{ $subscriber->name ?: 'Not supplied' }}
Email: {{ $subscriber->email }}
Source: {{ $subscriber->source ?: 'Not supplied' }}
@endif

@if($event === 'payment_attention')
Provider: {{ $provider ?? 'Stripe' }}
Reference: {{ $reference ?? 'Not available' }}
Summary: {{ $summary ?? 'A payment-processing event requires review.' }}
@endif

@if($actionUrl)
{{ $actionLabel }}: {{ $actionUrl }}
@endif
