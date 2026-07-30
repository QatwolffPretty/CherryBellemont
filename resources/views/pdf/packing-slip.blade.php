<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing Slip {{ $order->order_number ?? $order->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #3b0f1f; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.5; margin: 0; }
        .page { padding: 32px 36px; }
        .header, .address-grid { width: 100%; }
        .header td, .address-grid td { vertical-align: top; }
        .logo { max-height: 42px; max-width: 150px; margin-bottom: 7px; }
        .brand { color: #4a1023; font-size: 21px; font-weight: bold; letter-spacing: .8px; margin: 0; }
        .title { color: #b89246; font-size: 23px; font-weight: bold; letter-spacing: 1.6px; margin: 0; text-align: right; }
        .muted { color: #6b3044; }
        .gold-rule { background: #b89246; height: 1px; margin: 16px 0 19px; }
        .section-title { color: #4a1023; font-size: 10px; font-weight: bold; letter-spacing: 1px; margin: 0 0 7px; text-transform: uppercase; }
        .address-grid { margin-bottom: 20px; }
        .address-grid td { border-top: 1px solid #d9c18a; padding: 10px 14px 0 0; width: 50%; }
        .items { border-collapse: collapse; width: 100%; }
        .items th { background: #4a1023; color: #fffdf9; font-size: 9px; letter-spacing: .7px; padding: 8px; text-align: left; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e7dcc8; padding: 9px 8px; vertical-align: middle; }
        .thumb { height: 40px; width: 34px; }
        .qty { text-align: center; width: 70px; }
        .check { border: 1px solid #6b3044; display: inline-block; height: 14px; width: 14px; }
        .notes { border: 1px solid #d9c18a; margin-top: 20px; min-height: 66px; padding: 10px; }
        .footer { border-top: 1px solid #d9c18a; color: #6b3044; font-size: 9px; margin-top: 30px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    <main class="page">
        <table class="header"><tr>
            <td>
                @if($logo)<img class="logo" src="{{ $logo }}" alt="{{ $companyName }} logo">@endif
                <p class="brand">{{ $companyName }}</p>
            </td>
            <td>
                <p class="title">PACKING SLIP</p>
                <p class="muted" style="text-align:right; margin:5px 0 0;">Order {{ $order->order_number ?? $order->number }} · {{ optional($order->created_at)->format('d M Y') }}</p>
            </td>
        </tr></table>
        <div class="gold-rule"></div>

        <table class="address-grid"><tr>
            <td>
                <p class="section-title">Customer</p>
                <p style="margin:0;">{{ $order->customer_name ?: $order->full_name ?: 'Customer' }}<br>{{ $order->customer_phone ?: $order->phone ?: '—' }}</p>
            </td>
            <td>
                <p class="section-title">{{ $isPickup ? 'Pickup details' : 'Deliver to' }}</p>
                @foreach($deliveryLines as $line)<p style="margin:0;">{{ $line }}</p>@endforeach
                <p class="muted" style="margin:7px 0 0;">{{ $order->shipping_method_name ?: 'Delivery method to be confirmed' }}</p>
            </td>
        </tr></table>

        <table class="address-grid"><tr>
            <td>
                <p class="section-title">Courier & tracking</p>
                <p style="margin:0;">Courier: {{ $order->courier_name ?: 'Not assigned' }}<br>Service: {{ $order->latestShipment?->service_name ?: 'Not assigned' }}<br>Tracking: {{ $order->tracking_number ?: 'Not assigned' }}</p>
            </td>
            <td>
                <p class="section-title">Packing overview</p>
                <p style="margin:0;">Payment status: {{ str($order->payment_status ?: 'pending')->title() }}<br>Total items: {{ $totalItemCount }}</p>
                @if($order->gift_wrapping)
                    <p style="color:#4a1023;font-weight:bold;margin:7px 0 0;">GIFT ORDER · Signature Gift Experience</p>
                    <p class="section-title" style="margin-top:10px;">Gift message</p>
                    <p style="margin:0;">{{ $order->gift_message ?: 'No personalised gift message.' }}</p>
                @endif
            </td>
        </tr></table>

        <p class="section-title">Items to pack</p>
        <table class="items">
            <thead><tr><th style="width:45px;">Image</th><th>Product</th><th class="qty">Quantity</th><th style="width:62px; text-align:center;">Packed</th></tr></thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>@if($item['image'])<img class="thumb" src="{{ $item['image'] }}" alt="">@endif</td>
                        <td>{{ $item['name'] }}@if($item['variant_description'])<br><span class="muted">{{ $item['variant_description'] }}</span>@endif<br><span class="muted">SKU: {{ $item['sku'] ?: 'Not available' }}</span></td>
                        <td class="qty">{{ $item['quantity'] }}</td>
                        <td style="text-align:center;"><span class="check"></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="notes">
            <p class="section-title">Delivery instructions</p>
            <p style="margin:0;">{{ $order->delivery_instructions ?: 'No delivery instructions.' }}</p>
            <p class="section-title" style="margin-top:10px;">Packing notes</p>
            <p style="margin:0;">{{ $order->admin_notes ?: 'No packing notes.' }}</p>
        </div>

        <footer class="footer">{{ $companyName }} · {{ $supportEmail }} · Order {{ $order->order_number ?? $order->number }}</footer>
    </main>
</body>
</html>
