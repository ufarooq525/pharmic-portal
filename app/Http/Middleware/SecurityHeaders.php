<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The portal holds private sales data, so it must never appear in search engines or AI answer
 * engines. X-Robots-Tag covers files (PDF, CSV) that cannot carry a <meta name="robots"> tag.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noai, noimageai');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // A response may allow this site to frame it (the PDF shown in the import review drawer); everything else: no framing at all.
        $framable = $response->headers->get('X-Frame-Options') === 'SAMEORIGIN';
        $response->headers->set('X-Frame-Options', $framable ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Only our own scripts, styles, fonts and images; no framing; forms post only to this site.
        // Skipped while the Vite dev server is running, which serves assets from another port.
        if ($framable) {
            // The browser's own PDF viewer renders it; only who may frame it matters here.
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        } elseif (! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",   // the small theme script runs before first paint
                "style-src 'self' 'unsafe-inline'",    // inline style="width: …%" on bars and charts
                "img-src 'self' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
