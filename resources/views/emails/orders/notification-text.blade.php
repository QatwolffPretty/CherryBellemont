Cherry Bellemont

Order {{ $order->order_number }}
Payment: {{ $order->payment_status }}
Fulfilment: {{ $order->order_status }}
Total: RM {{ number_format($order->total, 2) }}

View your secure order: {{ $secureUrl }}
