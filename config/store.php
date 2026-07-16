<?php

$companyName = env('STORE_COMPANY_NAME', 'Cherry Bellemont');
$supportEmail = env('STORE_SUPPORT_EMAIL', 'support@cherrybellemont.com');
$generalEmail = env('STORE_GENERAL_EMAIL', 'hello@cherrybellemont.com');
$threadsUrl = env('STORE_THREADS_URL', 'https://threads.net/@yourusername');
$instagramUrl = env('STORE_INSTAGRAM_URL', 'https://instagram.com/yourusername');
$facebookUrl = env('STORE_FACEBOOK_URL', 'https://facebook.com/yourpage');

return [
    'company_name' => $companyName,
    'support_email' => $supportEmail,
    'general_email' => $generalEmail,
    'business_address' => env('STORE_BUSINESS_ADDRESS'),
    'logo_path' => env('STORE_LOGO_PATH', public_path('images/Cherry Red No BG.png')),
    'business_days' => [
        'weekdays' => 'Monday – Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday & Public Holidays',
    ],
    'business_hours' => [
        'weekdays' => '9:00 AM – 6:00 PM (MYT)',
        'saturday' => '10:00 AM – 3:00 PM',
        'sunday' => 'Closed',
    ],
    'threads_url' => $threadsUrl,
    'instagram_url' => $instagramUrl,
    'facebook_url' => $facebookUrl,
    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL'),
    'low_stock_threshold' => max(0, (int) env('LOW_STOCK_THRESHOLD', 3)),
    'contact' => [
        'support_email' => $supportEmail,
        'general_email' => $generalEmail,
        'business_days' => [
            'weekdays' => 'Monday – Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday & Public Holidays',
        ],
        'business_hours' => [
            'weekdays' => '9:00 AM – 6:00 PM (MYT)',
            'saturday' => '10:00 AM – 3:00 PM',
            'sunday' => 'Closed',
        ],
    ],
    'brand' => [
        'dark_wine' => '#4A1023',
        'gold' => '#B89246',
        'white' => '#FFFFFF',
        'ivory' => '#FCFAF7',
        'muted_burgundy' => '#6B3044',
    ],
];
