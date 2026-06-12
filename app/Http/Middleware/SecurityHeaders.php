<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

        // Tell the Vite helper to stamp its generated <script> tag with the nonce.
        app(Vite::class)->useCspNonce($nonce);

        $response = $next($request);

        // NOTE: 'unsafe-eval' is required by Alpine.js v3, which uses new Function()
        // internally to compile x-data/x-show/@click expressions. Removing it breaks
        // all Alpine interactivity. The proper long-term fix is to migrate to the
        // Alpine CSP build (@alpinejs/csp), which requires moving all component
        // definitions from inline HTML attributes into nonce-approved .js files —
        // a significant refactor. Until then, 'unsafe-eval' is an accepted risk:
        // all user-supplied content is HTML-escaped before rendering, limiting the
        // practical XSS surface that eval() could be chained with.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "script-src-attr 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
