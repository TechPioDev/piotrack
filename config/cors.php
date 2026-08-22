<?php

/*
 * Cross-origin config. Published (was framework default) to open the public chat
 * widget endpoints (wc/*) to customer websites: the widget is a token-scoped,
 * session-less client, so wildcard origins with credentials disabled is correct.
 * Everything else keeps the framework defaults.
 */
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'wc/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
