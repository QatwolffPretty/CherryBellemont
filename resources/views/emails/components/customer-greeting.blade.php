@php
    $brand = config('store.brand');
@endphp
<p style="margin:0 0 20px;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:17px;line-height:1.6;">Dear {{ $name ?: 'Customer' }},</p>
