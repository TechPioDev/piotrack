<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search-intelligence data sources (ADR-0005)
    |--------------------------------------------------------------------------
    | 'fixture' (default) returns deterministic results so the rank / AI
    | pipelines run and are tested without external accounts. The real drivers
    | require credentials.
    */
    'rank_provider' => env('SEO_RANK_PROVIDER', 'fixture'),

    // LINK: link-index driver ('fixture' ships; live = Ahrefs/GSC credentials + a class).
    'link_provider' => env('SEO_LINK_PROVIDER', 'fixture'),
    'ai_provider' => env('SEO_AI_PROVIDER', 'fixture'),

    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    // AI engines surfaced for visibility tracking.
    'ai_engines' => ['chatgpt', 'gemini', 'perplexity', 'copilot', 'ai_overview'],
];
