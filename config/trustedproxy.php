<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Behind a CDN or load balancer, request()->ip() collapses to the proxy's
    | address unless the forwarded headers are trusted — which silently breaks
    | every per-IP rate limit (the comment ceilings and the unlock throttle,
    | config/atelier.php). The framework's TrustProxies middleware reads this
    | value at request time.
    |
    | Set TRUSTED_PROXIES in production: '*' for a trusted-network LB that
    | terminates the connection, or a comma-separated list of proxy IPs/CIDRs.
    | Unset (null) trusts nothing — the correct default for direct/local serving.
    |
    | See docs/atelier.specs.md §10 and the deployment README.
    |
    */

    'proxies' => match (true) {
        env('TRUSTED_PROXIES') === '*' => '*',
        filled(env('TRUSTED_PROXIES')) => array_map('trim', explode(',', (string) env('TRUSTED_PROXIES'))),
        default => null,
    },

];
