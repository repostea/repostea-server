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
];
