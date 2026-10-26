<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\ViewAs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev Tools access gate.
 *
 * Reachable only by a real Admin directly, or by an admin "view as" session
 * whose current user is one of the disposable scratch accounts. A student or
 * teacher outside a preview never passes (403), and the scratch-target check
 * means a real learner account can never be touched through these routes.
 */
class EnsureDevToolsContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('devtools.enabled')) {
            abort(403);
        }

        $user = $request->user();

        if ($user !== null && $user->role === Role::Admin) {
            return $next($request);
        }

        if ($user !== null && ViewAs::isActive() && ViewAs::isScratchTarget($user)) {
            return $next($request);
        }

        abort(403);
    }
}
