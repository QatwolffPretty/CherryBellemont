@php
    $brand = config('store.brand');
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:20px 0;font-family:Arial,sans-serif;font-size:14px;">
    <tr><td style="padding:5px 0;color:{{ $brand['muted_burgundy'] }};">Subtotal</td><td align="right" style="padding:5px 0;color:{{ $brand['dark_wine'] }};">RM {{ number_format((float) $subtotal, 2) }}</td></tr>
    @if(($discount ?? 0) > 0)
        <tr><td style="padding:5px 0;color:{{ $brand['muted_burgundy'] }};">Product discount</td><td align="right" style="padding:5px 0;color:{{ $brand['dark_wine'] }};">-RM {{ number_format((float) $discount, 2) }}</td></tr>
    @endif
    <tr><td style="padding:5px 0;color:{{ $brand['muted_burgundy'] }};">Shipping{{ $shippingMethod ? ' ('.$shippingMethod.')' : '' }}</td><td align="right" style="padding:5px 0;color:{{ $brand['dark_wine'] }};">RM {{ number_format((float) $shippingFee, 2) }}</td></tr>
    @if(($freeShippingDiscount ?? 0) > 0)
        <tr><td style="padding:5px 0;color:{{ $brand['muted_burgundy'] }};">Free-shipping discount</td><td align="right" style="padding:5px 0;color:{{ $brand['dark_wine'] }};">-RM {{ number_format((float) $freeShippingDiscount, 2) }}</td></tr>
    @endif
    @if(($giftWrapping ?? false) && ($giftWrappingFee ?? 0) > 0)
        <tr><td style="padding:5px 0;color:{{ $brand['muted_burgundy'] }};">Signature Gift Experience</td><td align="right" style="padding:5px 0;color:{{ $brand['dark_wine'] }};">RM {{ number_format((float) $giftWrappingFee, 2) }}</td></tr>
    @endif
    <tr><td colspan="2" style="padding-top:10px;border-bottom:1px solid {{ $brand['gold'] }};font-size:1px;line-height:1px;">&nbsp;</td></tr>
    <tr><td style="padding-top:12px;color:{{ $brand['dark_wine'] }};font-size:16px;font-weight:bold;">Total</td><td align="right" style="padding-top:12px;color:{{ $brand['dark_wine'] }};font-size:16px;font-weight:bold;">RM {{ number_format((float) $total, 2) }}</td></tr>
</table>
