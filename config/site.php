<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Site Legal Information
    |--------------------------------------------------------------------------
    |
    | Configuration for site legal information used in legal pages,
    | privacy policy, terms of service, etc.
    |
    */

    'name' => env('SITE_NAME', 'Repostea'),

    'owner' => [
        'name' => env('SITE_OWNER_NAME', ''),
        'dni' => env('SITE_OWNER_DNI', ''),
    ],

    'contact' => [
        'email' => env('SITE_CONTACT_EMAIL', 'contact@example.com'),
    ],

    'purpose' => env('SITE_PURPOSE', 'Community content aggregation platform'),

    'jurisdiction' => env('SITE_JURISDICTION', 'Murcia'),

    /*
    |--------------------------------------------------------------------------
    | Site Branding / Logos
    |--------------------------------------------------------------------------
    |
    | Logo URLs for different contexts. Defaults to files in /public.
    | Set via env to customize per installation.
    |
    */

    'logo' => env('SITE_LOGO_URL', '/favicon.svg'),
    'logo_email' => env('SITE_LOGO_EMAIL_URL', '/logo-email.jpg'),
    'favicon' => env('SITE_FAVICON_URL', '/favicon.ico'),
    'favicon_96' => env('SITE_FAVICON_96_URL', 'favicon-96x96.png'),
    'apple_touch_icon' => env('SITE_APPLE_TOUCH_ICON_URL', 'apple-touch-icon.png'),
];
