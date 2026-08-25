<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Language-model driver (ADR-0008)
    |--------------------------------------------------------------------------
    | `fixture` is the tested default and requires no credentials. `openai` and
    | `anthropic` are real drivers that are untested here (no keys).
    */
    'driver' => env('AI_DRIVER', 'fixture'),

    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),

    /*
    | Bounded retry for transient provider failures (timeouts, 429, 5xx).
    */
    'max_attempts' => (int) env('AI_MAX_ATTEMPTS', 3),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost estimation (AIPF-003)
    |--------------------------------------------------------------------------
    | Price in minor units (cents) per 1,000,000 tokens, per model. Used to
    | attribute estimated spend per tenant/user/feature. Unknown models fall back
    | to `default`.
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'pricing' => [
        'default' => ['prompt' => 100, 'completion' => 300],
        'fixture-1' => ['prompt' => 0, 'completion' => 0],
        'gpt-4o-mini' => ['prompt' => 15, 'completion' => 60],
        'claude-sonnet-5' => ['prompt' => 300, 'completion' => 1500],
        'claude-haiku-4-5-20251001' => ['prompt' => 100, 'completion' => 500],
        'gemini-2.5-flash' => ['prompt' => 30, 'completion' => 250],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive action types (AIPF-006)
    |--------------------------------------------------------------------------
    | Actions the agent may only PROPOSE. They require an explicit human
    | confirmation before execution and can never be auto-executed.
    */
    'sensitive_actions' => ['send_email', 'update_crm', 'book_meeting'],
];
