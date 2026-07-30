@php($brand = config('store.brand'))
@component('emails.layouts.transactional', ['preheader' => 'Cherry Bellemont email delivery test'])
    <p style="margin:0;color:{{ $brand['gold'] }};font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Email Settings</p>
    <h1 style="margin:12px 0 16px;color:{{ $brand['dark_wine'] }};font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:normal;line-height:1.25;">{{ $subjectLine }}</h1>
    <p style="margin:0;color:{{ $brand['muted_burgundy'] }};font-family:Georgia,'Times New Roman',serif;font-size:16px;line-height:1.7;">{{ $messageBody }}</p>
@endcomponent
