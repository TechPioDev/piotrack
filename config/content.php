<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content distribution & reputation data sources (ADR-0007)
    |--------------------------------------------------------------------------
    | 'fixture' (default) returns deterministic results so the social + review
    | pipeline runs and is tested without accounts. 'live' uses the real vendor
    | drivers (require credentials + INTG OAuth).
    */
    'social_provider' => env('CONTENT_SOCIAL_PROVIDER', 'fixture'),
    'review_provider' => env('CONTENT_REVIEW_PROVIDER', 'fixture'),

    // SOC-022/023/024: social-listening driver ('fixture' ships; live = provider credentials + a class).
    'listening_provider' => env('CONTENT_LISTENING_PROVIDER', 'fixture'),

    'linkedin' => ['access_token' => env('LINKEDIN_SOCIAL_TOKEN')],
    'facebook' => ['access_token' => env('META_SOCIAL_TOKEN')],
    'x' => ['access_token' => env('X_SOCIAL_TOKEN')],
    'youtube' => ['access_token' => env('YOUTUBE_SOCIAL_TOKEN')],

    'google' => ['api_key' => env('GOOGLE_PLACES_KEY')],
];
