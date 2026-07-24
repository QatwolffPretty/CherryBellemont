Subtotal: RM {{ number_format((float) $order->subtotal, 2) }}
Shipping: RM {{ number_format((float) $order->shipping_fee, 2) }}
@if($order->gift_wrapping)
Signature Gift Experience: RM {{ number_format((float) $order->gift_wrapping_fee, 2) }}
@endif
Total: RM {{ number_format((float) $order->total, 2) }}
