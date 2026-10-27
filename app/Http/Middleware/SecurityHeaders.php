<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=()');

        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Pages must never be heuristically cached by browsers: a cached HTML
        // copy references a stale JS bundle hash, so phones keep running old
        // behavior (e.g. the search field that auto-submitted on typing).
        // Hashed build assets keep their own long-lived caching untouched.
        $response->headers->set('Cache-Control', 'no-store, must-revalidate');

        return $response;
    }
}
