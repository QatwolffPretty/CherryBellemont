@component('emails.layouts.transactional', ['preheader' => $product->name.' is available again.'])
    @php($brand = config('store.brand'))
    <p style="margin:0 0 10px;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">Back in stock</p>
    <h1 style="margin:0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;line-height:1.3;">Your Cherry Bellemont piece is available again.</h1>
    @if($notification->name)
        <p style="margin:22px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Arial,sans-serif;font-size:16px;line-height:1.7;">Dear {{ $notification->name }},</p>
    @endif
    <p style="margin:18px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Arial,sans-serif;font-size:16px;line-height:1.7;"><strong>{{ $product->name }}</strong> is currently available in the collection.</p>

    @if($product->image_path)
        <img src="{{ url(\Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path)) }}" alt="{{ $product->name }}" width="420" style="display:block;width:100%;max-width:420px;height:auto;margin:26px auto 0;border:0;">
    @endif

    <p style="margin:22px 0 0;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.4;">RM {{ number_format($product->price, 2) }}</p>
    <p style="margin:14px 0 0;color:{{ $brand['muted_burgundy'] }};font-family:Arial,sans-serif;font-size:14px;line-height:1.7;">Availability is limited and items are not reserved until checkout is completed.</p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0 0;">
        <tr><td style="background-color:{{ $brand['dark_wine'] }};"><a href="{{ $productUrl }}" style="display:inline-block;padding:14px 22px;color:{{ $brand['white'] }};font-family:Arial,sans-serif;font-size:14px;font-weight:bold;text-decoration:none;">View Product</a></td></tr>
    </table>

    <p style="margin:32px 0 0;padding-top:20px;border-top:1px solid {{ $brand['gold'] }};color:{{ $brand['muted_burgundy'] }};font-family:Arial,sans-serif;font-size:12px;line-height:1.65;">No longer interested? <a href="{{ $cancelUrl }}" style="color:{{ $brand['dark_wine'] }};text-decoration:underline;">Cancel this back-in-stock notification</a>.</p>
@endcomponent
