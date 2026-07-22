@php
    $brand = config('store.brand');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>{{ config('store.company_name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:{{ $brand['ivory'] }};color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;">
    @if($preheader ?? false)
        <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;opacity:0;color:transparent;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0;padding:0;background-color:{{ $brand['ivory'] }};">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background-color:{{ $brand['white'] }};">
                    @include('emails.components.header', [
                        'logoPath' => $logoPath ?? null,
                        'logoWidth' => $logoWidth ?? null,
                    ])
                    <tr>
                        <td style="padding:40px 36px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    @include('emails.components.footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
