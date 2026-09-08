<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact enrichment driver (CRM-027)
    |--------------------------------------------------------------------------
    | 'fixture' (default) returns deterministic firmographics labeled simulated
    | in the UI. A live driver (Clearbit / Apollo / ZoomInfo) is credentials
    | plus one class registered in AppServiceProvider.
    */
    'enrichment_provider' => env('CRM_ENRICHMENT_PROVIDER', 'fixture'),
];
