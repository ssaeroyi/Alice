<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps staff (admins and teachers) out of the student-facing zone.
 *
 * Admin accounts already had this protection under the old `not.admin`
 * alias; teachers are staff too — they live in the teacher portal and
 * never need the placement steps, hub, library or settings pages.
 */
class EnsureNotStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role->value, ['admin', 'teacher'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
