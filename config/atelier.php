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

    /*
    |--------------------------------------------------------------------------
    | Data protection (GDPR / ePrivacy)
    |--------------------------------------------------------------------------
    |
    | The privacy posture for the client PII Atelier captures at comment time
    | (name + email, ADR-0003) and the persistent `atelier_commenter` cookie.
    | `contact_email` is where data-subject requests are sent; `retention` is
    | the plain-language retention statement shown on the privacy policy.
    |
    | See docs/atelier.specs.md §13 (data protection) and the privacy policy.
    |
    */

    'privacy' => [
        'contact_email' => env('ATELIER_PRIVACY_CONTACT', 'privacy@redheadit.nl'),
        'retention' => env(
            'ATELIER_PRIVACY_RETENTION',
            'for as long as the project is active; erased on request or when the project is deleted.',
        ),
    ],

];
