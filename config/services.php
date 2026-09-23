<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // CALL-004: speech-to-text driver ('fixture' ships; live drivers are
    // credentials + a TranscriptionProvider implementation).
    'transcription' => [
        'driver' => env('TRANSCRIPTION_DRIVER', 'fixture'),
    ],

    // PRIV-006: shared secret the ESP bounce/complaint webhook must present.
    // Unset = the endpoint refuses everything.
    'email_webhook' => [
        'secret' => env('EMAIL_WEBHOOK_SECRET'),
    ],

    /*
     * OAuth connectors (INTG-001). A provider is configuration, not code: put
     * the app registration's id and secret in the environment and the generic
     * flow does the rest. Microsoft 365 is what a booking uses to read the
     * team's real free/busy and to put the meeting - with its Teams link - in
     * their calendar; 'common' lets any work account connect, and a single
     * tenant id locks it to one organisation.
     */
    'connectors' => [
        'microsoft_365' => [
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'authorize_url' => 'https://login.microsoftonline.com/'.env('MICROSOFT_TENANT', 'common').'/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/'.env('MICROSOFT_TENANT', 'common').'/oauth2/v2.0/token',
            'scopes' => 'offline_access openid email User.Read Calendars.ReadWrite Calendars.Read.Shared OnlineMeetings.ReadWrite',
        ],

        'google_calendar' => [
            'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scopes' => 'openid email https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly',
            // Google hands back a refresh token only when asked, and only the
            // first time somebody consents - so ask every time.
            'params' => ['access_type' => 'offline', 'prompt' => 'consent'],
        ],
    ],

];
