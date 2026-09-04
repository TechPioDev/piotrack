<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active payment provider
    |--------------------------------------------------------------------------
    | 'manual' (default) runs the whole lifecycle in-database (offline billing).
    | 'stripe' uses the real Stripe driver (requires keys; see ADR-0003).
    */
    'provider' => env('BILLING_PROVIDER', 'manual'),

    'currency' => env('BILLING_CURRENCY', 'USD'),

    // Grace period (days) after a payment fails before a subscription is suspended.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'manual' => [
        // Shared secret authenticating inbound events to /webhooks/manual.
        'webhook_secret' => env('BILLING_MANUAL_WEBHOOK_SECRET', 'local-manual-secret'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // BILL-018: the hosted customer-portal link (no-code portal URL from
        // the Stripe dashboard) where customers manage their payment method.
        'portal_url' => env('STRIPE_PORTAL_URL'),
    ],
];
