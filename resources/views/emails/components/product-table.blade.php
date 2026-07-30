@php
    $brand = config('store.brand');
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:24px 0;font-family:Arial,sans-serif;">
    <tr>
        <th align="left" style="padding:10px 0;border-bottom:1px solid {{ $brand['gold'] }};color:{{ $brand['muted_burgundy'] }};font-size:11px;letter-spacing:1.3px;text-transform:uppercase;">Item</th>
        <th align="center" style="padding:10px 0;border-bottom:1px solid {{ $brand['gold'] }};color:{{ $brand['muted_burgundy'] }};font-size:11px;letter-spacing:1.3px;text-transform:uppercase;">Qty</th>
        <th align="right" style="padding:10px 0;border-bottom:1px solid {{ $brand['gold'] }};color:{{ $brand['muted_burgundy'] }};font-size:11px;letter-spacing:1.3px;text-transform:uppercase;">Total</th>
    </tr>
    @foreach($items as $item)
        @php
            $name = $item->product_name ?? $item->name ?? 'Cherry Bellemont item';
            $lineTotal = $item->line_total ?? $item->total ?? 0;
            $variant = $item->variant_description ?: collect([$item->colour_name, $item->size_name])->filter()->implode(' / ');
        @endphp
        <tr>
            <td style="padding:14px 10px 14px 0;border-bottom:1px solid rgba(184,146,70,.28);color:{{ $brand['dark_wine'] }};font-size:14px;line-height:1.5;">{{ $name }}@if($variant)<br><span style="color:{{ $brand['muted_burgundy'] }};font-size:12px;">{{ $variant }}@if($item->sku) · {{ $item->sku }}@endif</span>@endif</td>
            <td align="center" style="padding:14px 6px;border-bottom:1px solid rgba(184,146,70,.28);color:{{ $brand['muted_burgundy'] }};font-size:14px;">{{ $item->quantity }}</td>
            <td align="right" style="padding:14px 0 14px 10px;border-bottom:1px solid rgba(184,146,70,.28);color:{{ $brand['dark_wine'] }};font-size:14px;">RM {{ number_format((float) $lineTotal, 2) }}</td>
        </tr>
    @endforeach
</table>
