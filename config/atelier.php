<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox origin for HTML bundles
    |--------------------------------------------------------------------------
    |
    | HTML "pages" are unpacked into a dedicated storage directory that is the
    | document root of a separate, PHP-less vhost. `path` is where the app
    | writes bundles; `url` is the public origin the sandbox vhost serves them
    | from. These two must point at the same directory on the box.
    |
    | See docs/atelier.specs.md §6 (sandbox architecture).
    |
    */

    'sandbox' => [
        'path' => env('ATELIER_SANDBOX_PATH', dirname(base_path()).'/atelier-sandbox'),
        'url' => env('ATELIER_SANDBOX_URL', 'http://atelier-sandbox.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token lengths (CSPRNG bytes)
    |--------------------------------------------------------------------------
    |
    | Number of random bytes for the project slug and the per-project sandbox
    | token. Each byte becomes 2 hex chars, so 24 bytes => 48-char token.
    |
    */

    'slug_bytes' => 24,
    'sandbox_token_bytes' => 24,

];
