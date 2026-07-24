<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Credit Note {{ $creditNoteNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #3b0f1f; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.55; margin: 0; }
        .page { padding: 34px 38px; } .header { width: 100%; } .header td { vertical-align: top; }
        .brand { color: #4a1023; font-size: 22px; font-weight: bold; letter-spacing: .8px; margin: 0; } .logo { max-height: 44px; max-width: 160px; margin-bottom: 7px; }
        .title { color: #b89246; font-size: 25px; font-weight: bold; letter-spacing: 2px; margin: 0; text-align: right; }
        .muted { color: #6b3044; } .gold-rule { background: #b89246; height: 1px; margin: 18px 0 20px; }
        .grid { margin: 18px 0 22px; width: 100%; } .grid td { border-top: 1px solid #d9c18a; padding: 11px 12px 0 0; vertical-align: top; width: 50%; }
        .items, .totals { border-collapse: collapse; width: 100%; } .items th { background: #4a1023; color: #fffdf9; font-size: 9px; letter-spacing: .8px; padding: 8px; text-align: left; text-transform: uppercase; } .items td { border-bottom: 1px solid #e7dcc8; padding: 9px 8px; vertical-align: top; }
        .right { text-align: right; } .totals { margin-left: auto; margin-top: 18px; width: 260px; } .totals td { padding: 4px 0; } .totals .grand td { border-top: 1px solid #b89246; color: #4a1023; font-size: 13px; font-weight: bold; padding-top: 8px; }
        .footer { border-top: 1px solid #d9c18a; color: #6b3044; font-size: 9px; margin-top: 34px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body><main class="page">
    <table class="header"><tr><td>@if($logo)<img class="logo" src="{{ $logo }}" alt="{{ $companyName }} logo">@endif<p class="brand">{{ $companyName }}</p><p class="muted">{{ $supportEmail }}</p></td><td><p class="title">CREDIT NOTE</p><p class="right">Credit note: {{ $creditNoteNumber }}<br>Refund: {{ $refund->refund_number }}<br>Order: {{ $order->order_number }}<br>Confirmed: {{ $refund->confirmed_at?->format('d M Y') ?? '—' }}</p></td></tr></table>
    <div class="gold-rule"></div>
    <table class="grid"><tr><td><strong>Issued to</strong><br>{{ $order->customer_name ?: 'Customer' }}<br>{{ $order->customer_email ?: '—' }}<br>{{ $order->customer_phone ?: '—' }}</td><td><strong>Refund details</strong><br>Provider: {{ str($refund->payment_provider)->title() }}<br>Status: {{ str($refund->status)->title() }}<br>Reason: {{ $refund->reason ?: 'Approved return resolution.' }}</td></tr></table>
    <table class="items"><thead><tr><th>Original order items</th><th class="right">Quantity</th><th class="right">Snapshot amount</th></tr></thead><tbody>@foreach($refund->returnRequest?->items ?? [] as $item)<tr><td>{{ $item->product_name }}</td><td class="right">{{ $item->approved_quantity ?? $item->requested_quantity }}</td><td class="right">RM {{ number_format((float) $item->line_paid_amount, 2) }}</td></tr>@endforeach</tbody></table>
    <table class="totals"><tr><td>Product refund</td><td class="right">RM {{ number_format((float) ($refund->amount - $refund->shipping_refund_amount - $refund->gift_wrap_refund_amount), 2) }}</td></tr><tr><td>Shipping refund</td><td class="right">RM {{ number_format((float) $refund->shipping_refund_amount, 2) }}</td></tr><tr><td>Gift wrapping refund</td><td class="right">RM {{ number_format((float) $refund->gift_wrap_refund_amount, 2) }}</td></tr><tr class="grand"><td>Refund total</td><td class="right">RM {{ number_format((float) $refund->amount, 2) }}</td></tr></table>
    <p class="footer">{{ $companyName }} · {{ $supportEmail }}<br>This document records a confirmed refund. It is not a payment card statement.</p>
</main></body></html>
