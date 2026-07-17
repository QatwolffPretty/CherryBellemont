{{ config('store.company_name') }}

@if($subscriber->name)Dear {{ $subscriber->name }},

@endif{!! trim(strip_tags($campaign->content)) !!}

@if($campaign->cta_text && $campaign->cta_url)
{{ $campaign->cta_text }}: {{ $campaign->cta_url }}
@endif

Unsubscribe: {{ $unsubscribeUrl }}

Need help? {{ config('store.support_email') }}
