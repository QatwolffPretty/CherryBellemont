@php
    $brand = config('store.brand');
    $settings = app(\App\Services\SettingsService::class);
    $companyName = $settings->get('store.company_name', config('store.company_name'));
    $logoPath = $logoPath ?? $settings->imageUrl($settings->get('store.logo_light'), asset('images/Cherry White No BG.png'));
    $logoWidth = $logoWidth ?? 42;
@endphp
<tr>
    <td align="center" style="padding:30px 28px;background-color:{{ $brand['dark_wine'] }};">
        <img src="{{ $logoPath }}" width="{{ $logoWidth }}" alt="{{ $companyName }} monogram" style="display:block;margin:0 auto 14px;border:0;outline:none;text-decoration:none;">
        <p style="margin:0;color:{{ $brand['white'] }};font-family:Georgia,'Times New Roman',serif;font-size:24px;letter-spacing:5px;line-height:1.2;">{{ strtoupper($companyName) }}</p>
        <p style="margin:10px 0 0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:10px;letter-spacing:3px;text-transform:uppercase;">The House of Quiet Distinction</p>
    </td>
</tr>
