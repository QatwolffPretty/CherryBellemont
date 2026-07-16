@php
    $brand = config('store.brand');
    $tones = [
        'pending' => ['background' => $brand['ivory'], 'border' => $brand['gold'], 'text' => $brand['dark_wine']],
        'success' => ['background' => $brand['dark_wine'], 'border' => $brand['dark_wine'], 'text' => $brand['white']],
        'shipped' => ['background' => $brand['white'], 'border' => $brand['muted_burgundy'], 'text' => $brand['muted_burgundy']],
    ];
    $style = $tones[$tone ?? 'pending'] ?? $tones['pending'];
@endphp
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0;">
    <tr>
        <td style="padding:7px 10px;border:1px solid {{ $style['border'] }};background-color:{{ $style['background'] }};color:{{ $style['text'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.4px;text-transform:uppercase;">{{ $label }}</td>
    </tr>
</table>
