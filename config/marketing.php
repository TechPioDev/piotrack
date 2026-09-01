<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messaging providers (ADR-0004)
    |--------------------------------------------------------------------------
    | 'log' (default) records sends to the log and returns accepted — the whole
    | pipeline (recipients, suppression, tracking, analytics) runs against it.
    | 'smtp' (email) / 'twilio' (sms) are real drivers requiring credentials.
    */
    'mail_provider' => env('MARKETING_MAIL_PROVIDER', 'log'),
    'sms_provider' => env('MARKETING_SMS_PROVIDER', 'log'),

    // Default From identity for marketing email (per-campaign override allowed).
    'from' => [
        'name' => env('MARKETING_FROM_NAME', env('APP_NAME', 'Piotrack')),
        'email' => env('MARKETING_FROM_EMAIL', 'marketing@piotrack.test'),
    ],

    // Twilio credentials (only used when sms_provider=twilio).
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    // How many recipients a single campaign-send job processes per chunk.
    'send_chunk' => (int) env('MARKETING_SEND_CHUNK', 100),

    // Chat-widget public key embedded on the product marketing pages (MSITE).
    // Per environment — the key belongs to that environment's PioTrack org
    // widget. Unset (default) renders the pages without a chat widget.
    'chat_widget_key' => env('MARKETING_CHAT_WIDGET_KEY'),
];
