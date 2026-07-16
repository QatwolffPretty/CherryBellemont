<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #3b0f1f; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; margin: 0; }
        .page { padding: 34px 38px; }
        .header, .address-grid { width: 100%; }
        .header td { vertical-align: top; }
        .brand { color: #4a1023; font-size: 22px; font-weight: bold; letter-spacing: .8px; margin: 0; }
        .logo { max-height: 44px; max-width: 160px; margin-bottom: 7px; }
        .invoice-title { color: #b89246; font-size: 25px; font-weight: bold; letter-spacing: 2px; margin: 0; text-align: right; }
        .muted { color: #6b3044; }
        .gold-rule { background: #b89246; height: 1px; margin: 18px 0 20px; }
        .section-title { color: #4a1023; font-size: 10px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .details { margin: 8px 0 0; }
        .details td { padding: 2px 0; vertical-align: top; }
        .details .label { color: #6b3044; padding-right: 16px; width: 95px; }
        .address-grid { margin: 18px 0 22px; }
        .address-grid td { border-top: 1px solid #d9c18a; padding: 11px 12px 0 0; vertical-align: top; width: 50%; }
        .items { border-collapse: collapse; margin-top: 8px; width: 100%; }
        .items th { background: #4a1023; color: #fffdf9; font-size: 9px; letter-spacing: .8px; padding: 8px; text-align: left; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e7dcc8; padding: 9px 8px; vertical-align: top; }
        .right { text-align: right; }
        .totals { border-collapse: collapse; margin-left: auto; margin-top: 18px; width: 245px; }
        .totals td { padding: 4px 0; }
        .totals .grand td { border-top: 1px solid #b89246; color: #4a1023; font-size: 13px; font-weight: bold; padding-top: 8px; }
        .status { color: #4a1023; font-weight: bold; text-transform: capitalize; }
        .footer { border-top: 1px solid #d9c18a; color: #6b3044; font-size: 9px; margin-top: 34px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    <main class="page">
        <table class="header">
            <tr>
                <td>
                    @if($logo)<img class="logo" src="{{ $logo }}" alt="{{ $companyName }} logo">@endif
                    <p class="brand">{{ $companyName }}</p>
                    <p class="muted">{{ $supportEmail }}</p>
                </td>
                <td>
                    <p class="invoice-title">INVOICE</p>
                    <table class="details" style="margin-left:auto;">
                        <tr><td class="label">Invoice No.</td><td>{{ $invoiceNumber }}</td></tr>
                        <tr><td class="label">Order No.</td><td>{{ $order->order_number ?? $order->number }}</td></tr>
                        <tr><td class="label">Invoice Date</td><td>{{ optional($order->created_at)->format('d M Y') }}</td></tr>
                        @if($paymentDate)<tr><td class="label">Payment Date</td><td>{{ $paymentDate->format('d M Y') }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <div class="gold-rule"></div>

        <table class="address-grid">
            <tr>
                <td>
                    <p class="section-title">Billed to</p>
                    <p>{{ $order->customer_name ?: $order->full_name ?: 'Customer' }}<br>
                    {{ $order->customer_email ?: $order->email ?: '—' }}<br>
                    {{ $order->customer_phone ?: $order->phone ?: '—' }}</p>
                </td>
                <td>
                    <p class="section-title">{{ $isPickup ? 'Pickup details' : 'Delivery address' }}</p>
                    @foreach($deliveryLines as $line)<p style="margin:0;">{{ $line }}</p>@endforeach
                    <p class="muted" style="margin-top:7px;">{{ $order->shipping_method_name ?: 'Delivery method to be confirmed' }}</p>
                </td>
            </tr>
        </table>

        <p class="section-title">Order items</p>
        <table class="items">
            <thead><tr><th>Item</th><th class="right">Quantity</th><th class="right">Unit price</th><th class="right">Line total</th></tr></thead>
            <tbody>
                @foreach($items as $item)
                    <tr><td>{{ $item['name'] }}</td><td class="right">{{ $item['quantity'] }}</td><td class="right">RM {{ number_format($item['unit_price'], 2) }}</td><td class="right">RM {{ number_format($item['line_total'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>Subtotal</td><td class="right">RM {{ number_format((float) $order->subtotal, 2) }}</td></tr>
            @if($order->coupon_code)<tr><td>Coupon ({{ $order->coupon_code }})</td><td></td></tr>@endif
            @if((float) ($order->discount_amount ?? 0) > 0)<tr><td>Discount</td><td class="right">−RM {{ number_format((float) $order->discount_amount, 2) }}</td></tr>@endif
            <tr><td>Shipping fee</td><td class="right">RM {{ number_format((float) $order->shipping_fee, 2) }}</td></tr>
            @if((float) ($order->free_shipping_discount ?? 0) > 0)<tr><td>Free-shipping discount</td><td class="right">−RM {{ number_format((float) $order->free_shipping_discount, 2) }}</td></tr>@endif
            <tr class="grand"><td>Total</td><td class="right">RM {{ number_format((float) $order->total, 2) }}</td></tr>
        </table>

        <table class="details" style="margin-top:20px;">
            <tr><td class="label">Payment Method</td><td>{{ ucfirst($order->payment_method ?: '—') }}</td></tr>
            <tr><td class="label">Payment Status</td><td class="status">{{ $order->payment_status ?: 'pending' }}</td></tr>
            <tr><td class="label">Fulfilment</td><td class="status">{{ str($order->order_status ?: 'pending')->replace('_', ' ')->title() }}</td></tr>
        </table>

        <footer class="footer">
            Need help? Contact {{ $supportEmail }}@if($businessAddress) · {{ $businessAddress }}@endif<br>
            Thank you for choosing {{ $companyName }}.
        </footer>
    </main>
</body>
</html>
