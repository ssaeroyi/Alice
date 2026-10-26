<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Block students from student-area pages until their placement is done.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_setup_complete) {
            return redirect()->route('placement.step1');
        }

        return $next($request);
    }
}
