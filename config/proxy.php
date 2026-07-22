<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Reverse Proxies
    |--------------------------------------------------------------------------
    |
    | Leave this empty for direct local development. In production, list only
    | the IP addresses or CIDR ranges of the hosting proxy or load balancer so
    | Laravel can safely honour forwarded HTTPS and client-IP headers.
    |
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),
];
