<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Competitor ad-library driver (CINT-002/003)
    |--------------------------------------------------------------------------
    | 'fixture' (default) returns deterministic transparency-style ads labeled
    | simulated in the UI. Live drivers plug in with a token + one class: the
    | Meta Ad Library API, or Google's Ads Transparency Center via SerpApi.
    */
    'ad_library_provider' => env('COMPETITIVE_AD_LIBRARY_PROVIDER', 'fixture'),
];
