<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectAuthenticatedHome
{
    /**
     * Send signed-in users straight to their home when they land on the
     * public landing page, instead of making them re-find a sign-in CTA.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $next($request);
        }

        $home = match ($user->role) {
            Role::Admin => route('admin.dashboard'),
            Role::Teacher => route('teacher.dashboard'),
            default => $user->is_setup_complete
                ? route('library')
                : route('placement.step1'),
        };

        return redirect($home);
    }
}
