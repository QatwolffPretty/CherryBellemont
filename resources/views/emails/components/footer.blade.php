@php
    $brand = config('store.brand');
@endphp
<tr>
    <td align="center" style="padding:30px 36px;background-color:{{ $brand['dark_wine'] }};color:{{ $brand['white'] }};font-family:Arial,sans-serif;font-size:13px;line-height:1.7;">
        <p style="margin:0;color:{{ $brand['gold'] }};font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Need Help?</p>
        <p style="margin:8px 0 0;"><a href="mailto:{{ config('store.support_email') }}" style="color:{{ $brand['white'] }};text-decoration:underline;">{{ config('store.support_email') }}</a></p>
        <p style="margin:14px 0 0;">
            <a href="{{ config('store.threads_url') }}" style="color:{{ $brand['gold'] }};text-decoration:none;">Threads</a>
            <span style="color:{{ $brand['gold'] }};padding:0 7px;">|</span>
            <a href="{{ config('store.instagram_url') }}" style="color:{{ $brand['gold'] }};text-decoration:none;">Instagram</a>
            <span style="color:{{ $brand['gold'] }};padding:0 7px;">|</span>
            <a href="{{ config('store.facebook_url') }}" style="color:{{ $brand['gold'] }};text-decoration:none;">Facebook</a>
        </p>
        <p style="margin:18px 0 0;color:rgba(255,255,255,.72);font-size:11px;">&copy; {{ date('Y') }} {{ config('store.company_name') }}. All rights reserved.</p>
    </td>
</tr>
