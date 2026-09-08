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

    // TSEO-023: Search Console driver ('fixture' ships; live = Google OAuth + a class).
    'search_console_provider' => env('SEO_SEARCH_CONSOLE_PROVIDER', 'fixture'),

    // TSEO-019: CWV field-data driver ('fixture' ships; live = PageSpeed Insights key + a class).
    'vitals_provider' => env('SEO_VITALS_PROVIDER', 'fixture'),

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

    // AIVIS-004: Perplexity's public OpenAI-compatible API.
    'perplexity' => [
        'key' => env('PERPLEXITY_API_KEY'),
        'model' => env('PERPLEXITY_MODEL', 'sonar'),
    ],

    // AIVIS-005: consumer Copilot has no public API; point this at the
    // Microsoft-supported OpenAI-compatible surface behind it (an Azure
    // OpenAI deployment, or a gateway to the M365 Copilot Chat API).
    'copilot' => [
        'endpoint' => env('SEO_COPILOT_ENDPOINT'),
        'key' => env('SEO_COPILOT_KEY'),
        'model' => env('SEO_COPILOT_MODEL', 'gpt-4o'),
    ],

    // AI engines surfaced for visibility tracking.
    'ai_engines' => ['chatgpt', 'gemini', 'perplexity', 'copilot', 'ai_overview'],
];
