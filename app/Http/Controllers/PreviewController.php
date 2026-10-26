<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Support\Audit;
use App\Support\ViewAs;
use Illuminate\Http\RedirectResponse;

/**
 * Escape hatch for admin "view as" previews (route: /preview/return).
 *
 * Deliberately outside the student (not.staff) and admin/teacher (role) route
 * groups so it is reachable from anywhere in a previewed session — including
 * the immersive reader — and restores the real admin session safely.
 */
class PreviewController extends Controller
{
    public function stop(): RedirectResponse
    {
        // Capture before stopping so the audit meta names who was previewed.
        $target = ViewAs::target();

        // stop() re-logs-in the real actor when a preview was active (and is a
        // safe no-op when none was), so redirect by the now-current user's role.
        $wasActive = ViewAs::stop();

        if ($wasActive && $target) {
            Audit::log('preview.stopped', $target, ['role' => $target->role->value]);

            // Reset the disposable scratch account so the next preview starts
            // clean and no preview activity lingers. Never touches a real user.
            ViewAs::resetTarget($target);
        }

        $user = auth()->user();

        $home = match ($user?->role) {
            Role::Admin => route('admin.dashboard'),
            Role::Teacher => route('teacher.dashboard'),
            default => route('hub'),
        };

        return redirect($home);
    }
}
