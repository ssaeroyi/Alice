<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the narration tooling feed used once Alice is hosted. Without a
 * configured NARRATION_FEED_TOKEN (or with a mismatched bearer token) the
 * routes vanish — the feed must never be discoverable on a bare install.
 */
class EnsureNarrationFeedToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('stories.narration.feed_token');

        if ($token === null || $token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            abort(404);
        }

        return $next($request);
    }
}
