@php
    $brand = config('store.brand');
@endphp
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0;">
    <tr>
        <td align="center" style="border:1px solid {{ $brand['gold'] }};">
            <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;color:{{ $brand['dark_wine'] }};font-family:Arial,sans-serif;font-size:12px;font-weight:bold;letter-spacing:1.5px;line-height:1;text-decoration:none;text-transform:uppercase;">{{ $slot }}</a>
        </td>
    </tr>
</table>
