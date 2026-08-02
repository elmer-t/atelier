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

    /*
    |--------------------------------------------------------------------------
    | Public comment abuse controls
    |--------------------------------------------------------------------------
    |
    | Commenting is passwordless and open to anyone holding a project link
    | (ADR-0003), so these limits are the only thing between the public form
    | and a flood of junk Users and Comments. They are deliberately far above
    | human pace: a real commenter never notices them.
    |
    | `posts_per_minute_per_ip` is the outer ceiling — it still bites when an
    | attacker rotates through fresh identities, which the per-commenter limit
    | alone would not catch. `min_seconds_before_submit` is the floor on how
    | fast identity can plausibly be typed; set it to 0 to disable the check.
    |
    | DEPLOYMENT: the per-IP ceiling is only as good as `request()->ip()`. Behind
    | a load balancer or CDN with no trusted-proxy configuration, every visitor
    | resolves to the proxy's address and that ceiling becomes a single global
    | budget — a self-inflicted outage rather than a control. Configure
    | `trustProxies()` in bootstrap/app.php before relying on it in production,
    | or raise it out of the way and lean on the per-commenter limit.
    |
    | See docs/atelier.specs.md §10 (security checklist).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | File-artifact upload allowlist
    |--------------------------------------------------------------------------
    |
    | `file`-type artifacts are streamed back inline on the app's own origin, so
    | an uploaded document that the browser treats as HTML/SVG is stored XSS on
    | that origin (ADR-0002 keeps executable HTML on the separate sandbox vhost
    | for exactly this reason). We therefore allowlist both the client extension
    | and the content-sniffed MIME type: neither `evil.html` nor a `.pdf` whose
    | bytes are actually HTML gets through. Executable types (html, svg, xml, js)
    | are deliberately absent. Kept alongside `X-Content-Type-Options: nosniff`
    | on the stream and a sandboxed preview iframe (specs §6/§10).
    |
    */

    'uploads' => [
        'file_extensions' => [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
            'txt', 'csv', 'md', 'rtf',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp', 'zip',
        ],

        'file_mimetypes' => [
            'application/pdf',
            'image/png', 'image/jpeg', 'image/gif', 'image/webp',
            'text/plain', 'text/csv', 'text/markdown', 'text/rtf', 'application/rtf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'application/zip',
        ],
    ],

    'comments' => [
        'max_body_length' => 5000,

        'min_seconds_before_submit' => (int) env('ATELIER_COMMENT_MIN_SECONDS', 2),

        'rate_limits' => [
            'identity_per_minute' => (int) env('ATELIER_COMMENT_IDENTITY_RATE', 5),
            'posts_per_minute_per_commenter' => (int) env('ATELIER_COMMENT_POST_RATE', 10),
            'posts_per_minute_per_ip' => (int) env('ATELIER_COMMENT_POST_IP_RATE', 20),
        ],
    ],

];
