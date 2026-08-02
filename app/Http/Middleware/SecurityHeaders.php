<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app origin carried no security headers, so nothing contained an XSS, stopped a
 * browser sniffing an uploaded file into HTML, or kept the admin UI out of a hostile
 * frame (specs §10). This adds that baseline to every app-origin response.
 *
 * The Content-Security-Policy is tuned to keep Livewire, Flux/Alpine and Vite working:
 * Alpine evaluates expressions with `new Function`, so `script-src` must allow
 * `unsafe-eval`, and both Livewire and Flux inject inline `<script>`/`<style>`. The
 * value is still far from open — it pins the framing origin, forbids plugins, and only
 * lets the HTML-artifact iframe reach the separate sandbox origin (ADR-0002). The
 * sandbox vhost is a different, PHP-less host and is out of scope here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        // Only meaningful over TLS; harmless to omit in local http development.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Build the policy, allowing the HTML-artifact iframe to reach the sandbox origin.
     */
    protected function contentSecurityPolicy(): string
    {
        $sandbox = $this->sandboxOrigin();

        $frameSrc = trim("'self' {$sandbox}");

        $directives = [
            "default-src 'self'",
            // Alpine needs unsafe-eval; Livewire and Flux inject inline scripts.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src {$frameSrc}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * The scheme://host[:port] of the sandbox vhost, so `frame-src` names an origin
     * rather than the raw configured string (which may carry a path).
     */
    protected function sandboxOrigin(): string
    {
        $url = (string) config('atelier.sandbox.url');

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme === null || $host === null) {
            return '';
        }

        $port = parse_url($url, PHP_URL_PORT);

        return $port !== null ? "{$scheme}://{$host}:{$port}" : "{$scheme}://{$host}";
    }
}
