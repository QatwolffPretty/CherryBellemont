@component('emails.layouts.transactional', ['preheader' => $campaign->preview_text ?: $campaign->subject])
    @php($brand = config('store.brand'))

    @if($isTest)
        <p style="margin:0 0 22px;padding:10px 12px;border:1px solid {{ $brand['gold'] }};color:{{ $brand['dark_wine'] }};font-family:Arial,sans-serif;font-size:12px;line-height:1.5;text-align:center;">This is a test email. It will not create a campaign delivery record.</p>
    @endif

    @if($campaign->hero_image_path)
        <img src="{{ url(\Illuminate\Support\Facades\Storage::disk('public')->url($campaign->hero_image_path)) }}" alt="{{ $campaign->name }}" width="568" style="display:block;width:100%;max-width:568px;height:auto;margin:0 0 28px;border:0;">
    @endif

    <p style="margin:0 0 10px;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">Cherry Bellemont</p>
    @if($subscriber->name)
        <p style="margin:0 0 18px;color:{{ $brand['muted_burgundy'] }};font-family:Arial,sans-serif;font-size:15px;line-height:1.65;">Dear {{ $subscriber->name }},</p>
    @endif

    <div style="color:{{ $brand['dark_wine'] }};font-family:Arial,sans-serif;font-size:16px;line-height:1.75;">
        {!! $campaign->content !!}
    </div>

    @if($campaign->cta_text && $campaign->cta_url)
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:30px 0 0;">
            <tr><td style="background-color:{{ $brand['dark_wine'] }};"><a href="{{ $campaign->cta_url }}" style="display:inline-block;padding:14px 22px;color:{{ $brand['white'] }};font-family:Arial,sans-serif;font-size:14px;font-weight:bold;letter-spacing:.4px;text-decoration:none;">{{ $campaign->cta_text }}</a></td></tr>
        </table>
    @endif

    <div style="margin-top:36px;padding-top:20px;border-top:1px solid {{ $brand['gold'] }};color:{{ $brand['muted_burgundy'] }};font-family:Arial,sans-serif;font-size:12px;line-height:1.65;">
        <p style="margin:0;">You are receiving this because you subscribed to Cherry Bellemont updates.</p>
        <p style="margin:8px 0 0;"><a href="{{ $unsubscribeUrl }}" style="color:{{ $brand['dark_wine'] }};text-decoration:underline;">Unsubscribe from these emails</a></p>
    </div>
@endcomponent
