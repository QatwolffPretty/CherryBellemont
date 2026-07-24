Cherry Bellemont

Order {{ $order->order_number }}
Payment: {{ $order->payment_status }}
Fulfilment: {{ $order->order_status }}
Payment method: {{ $order->payment_method === 'stripe' ? 'Stripe' : 'DuitNow' }}
Delivery method: {{ $order->shipping_method_name ?: 'To be confirmed' }}
Subtotal: RM {{ number_format($order->subtotal, 2) }}
@if(($order->discount_amount ?? 0) > 0)
Product discount: -RM {{ number_format($order->discount_amount, 2) }}
@endif
Shipping: RM {{ number_format($order->shipping_fee, 2) }}
@if(($order->free_shipping_discount ?? 0) > 0)
Free-shipping discount: -RM {{ number_format($order->free_shipping_discount, 2) }}
@endif
@if($order->gift_wrapping)
Signature Gift Experience: RM {{ number_format($order->gift_wrapping_fee, 2) }}
@if($order->gift_message)
Gift message: {{ $order->gift_message }}
@endif
@endif
Total: RM {{ number_format($order->total, 2) }}

@if($event === 'order_placed' && $order->payment_method === 'duitnow')
Payment is pending. Please scan the DuitNow QR code and upload your receipt.
@endif
@if($event === 'order_placed' && $order->payment_method === 'stripe')
Payment is pending and will be confirmed only after Stripe verifies it.
@endif
@if($event === 'receipt_submitted')
Your DuitNow receipt is pending review. Payment is not confirmed until administrator approval.
@endif
@if($event === 'receipt_rejected')
Payment remains pending. Reason: {{ $context['reason'] ?? 'Please upload a replacement receipt.' }}
@endif
@if($event === 'payment_approved')
Payment is paid.
@if($order->payment_method === 'stripe' && $order->stripe_paid_at)
Confirmed on {{ $order->stripe_paid_at->format('d M Y H:i') }}.
@endif
@endif
@if($event === 'status_updated' && $order->order_status === 'processing')
Your order is being prepared.
@endif
@if($event === 'status_updated' && $order->order_status === 'packed')
Your order has been packed and is being prepared for dispatch.
@endif
@if($event === 'status_updated' && $order->order_status === 'shipped')
Courier: {{ $order->courier_name ?: 'To be confirmed' }}
Tracking number: {{ $order->tracking_number ?: 'To be confirmed' }}
@if(! empty($context['tracking_url']))
Track shipment: {{ $context['tracking_url'] }}
@endif
@endif
@if($event === 'status_updated' && $order->order_status === 'delivered')
Your order has been delivered. Thank you for choosing Cherry Bellemont.
@endif
@if($event === 'status_updated' && $order->order_status === 'cancelled')
Cancellation reason: {{ $order->cancellation_reason ?: 'Please contact support for further information.' }}
@endif
@if($event === 'shipment_updated')
Shipment update: {{ str($context['shipment_status'] ?? 'updated')->replace('_', ' ')->title() }}
Courier: {{ $order->courier_name ?: 'To be confirmed' }}
Tracking number: {{ $order->tracking_number ?: 'To be confirmed' }}
@if($context['estimated_delivery_at'])
Estimated delivery: {{ $context['estimated_delivery_at'] }}
@endif
@endif

@if($secureUrl)
View your secure order: {{ $secureUrl }}
@endif
@if($event === 'order_placed' && $order->payment_method === 'duitnow' && $duitNowUrl)
Complete DuitNow payment: {{ $duitNowUrl }}
@endif
