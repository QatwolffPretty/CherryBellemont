@php
    $brand = config('store.brand');
@endphp
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0;">
    <tr>
        <td align="center" style="background-color:{{ $brand['dark_wine'] }};">
            <a href="{{ $url }}" style="display:inline-block;padding:14px 22px;color:{{ $brand['white'] }};font-family:Arial,sans-serif;font-size:12px;font-weight:bold;letter-spacing:1.5px;line-height:1;text-decoration:none;text-transform:uppercase;">{{ $slot }}</a>
        </td>
    </tr>
</table>
