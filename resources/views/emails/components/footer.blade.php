@php
    $brand = config('store.brand');
    $settings = app(\App\Services\SettingsService::class);
    $companyName = $settings->get('store.company_name', config('store.company_name'));
    $supportEmail = $settings->get('contact.support_email', config('store.support_email'));
@endphp
<tr>
    <td align="center" style="padding:30px 36px;background-color:{{ $brand['dark_wine'] }};color:{{ $brand['white'] }};font-family:Arial,sans-serif;font-size:13px;line-height:1.7;">
        <p style="margin:0;color:{{ $brand['gold'] }};font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Need Help?</p>
        <p style="margin:8px 0 0;"><a href="mailto:{{ $supportEmail }}" style="color:{{ $brand['white'] }};text-decoration:underline;">{{ $supportEmail }}</a></p>
        <p style="margin:14px 0 0;">
            <a href="{{ $settings->get('social.threads_url', config('store.threads_url')) }}" style="color:{{ $brand['gold'] }};text-decoration:none;">Threads</a>
            <span style="color:{{ $brand['gold'] }};padding:0 7px;">|</span>
            <a href="{{ $settings->get('social.instagram_url', config('store.instagram_url')) }}" style="color:{{ $brand['gold'] }};text-decoration:none;">Instagram</a>
            <span style="color:{{ $brand['gold'] }};padding:0 7px;">|</span>
            <a href="{{ $settings->get('social.facebook_url', config('store.facebook_url')) }}" style="color:{{ $brand['gold'] }};text-decoration:none;">Facebook</a>
        </p>
        <p style="margin:18px 0 0;color:rgba(255,255,255,.72);font-size:11px;">&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
    </td>
</tr>
