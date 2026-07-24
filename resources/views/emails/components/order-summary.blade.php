@include('emails.components.product-table', ['items' => $order->items])
@include('emails.components.order-totals', [
    'subtotal' => $order->subtotal,
    'shippingFee' => $order->shipping_fee,
    'total' => $order->total,
    'shippingMethod' => $order->shipping_method_name,
    'discount' => $order->discount_amount ?? 0,
    'freeShippingDiscount' => $order->free_shipping_discount ?? 0,
    'giftWrappingFee' => $order->gift_wrapping_fee ?? 0,
    'giftWrapping' => $order->gift_wrapping ?? false,
])
