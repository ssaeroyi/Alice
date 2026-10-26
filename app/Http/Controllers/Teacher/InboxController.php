<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Group;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The teacher's notification inbox. Notices arrive from student activity:
 * someone joined a class, accepted an invitation, or finished an assignment.
 * Following a notice marks it read and lands on the relevant management page.
 */
class InboxController extends Controller
{
    /**
     * The tab filters a teacher can browse. `class` merges both class-event
     * types (a student joined, or accepted an invitation) because they share
     * the same destination — the class page. Anything not on the list falls
     * back to `all`, so no unknown filter value ever leaks into the query.
     */
    private const FILTERS = ['all', 'unread', 'finished', 'class'];

    /**
     * How many rows the nav-bell preview shows. Deliberately small: the panel
     * is a glance layer, the inbox page is the full list.
     */
    private const PREVIEW_LIMIT = 6;

    public function index(Request $request): View
    {
        $filter = in_array($request->input('filter'), self::FILTERS, true)
            ? $request->input('filter')
            : 'all';

        $notifications = $request->user()->notifications()->latest();

        $notifications->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'finished', fn ($q) => $q->where('type', 'assignment.completed.teacher'))
            ->when($filter === 'class', fn ($q) => $q->whereIn('type', [
                'class.joined.teacher',
                'class.invite.accepted.teacher',
            ]));

        $base = $request->user()->notifications();

        return view('teacher.inbox', [
            'notifications' => $notifications->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                'all' => $base->count(),
                'unread' => (clone $base)->unread()->count(),
                'finished' => (clone $base)->where('type', 'assignment.completed.teacher')->count(),
                'class' => (clone $base)->whereIn('type', [
                    'class.joined.teacher',
                    'class.invite.accepted.teacher',
                ])->count(),
            ],
            'unreadCount' => (clone $base)->unread()->count(),
            'hasAny' => $base->exists(),
        ]);
    }

    /**
     * The nav bell's dropdown preview: the newest rows as an HTML fragment
     * plus the live unread count, fetched when the panel opens rather than at
     * page load. Unread rows come first, then recency, so the panel agrees
     * with the badge.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();

        $unread = $user->notifications()->unread()
            ->latest()
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $notifications = $unread->count() >= self::PREVIEW_LIMIT
            ? $unread
            : $unread->concat(
                $user->notifications()->whereNotNull('read_at')
                    ->latest()
                    ->limit(self::PREVIEW_LIMIT - $unread->count())
                    ->get()
            );

        $unreadCount = (int) $user->notifications()->unread()->count();

        return response()->json([
            'html' => view('teacher.partials.inbox-preview', [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
            ])->render(),
            'unread' => $unreadCount,
        ]);
    }

    /**
     * The visual pill a row shows, keyed by notification type. Unknown or
     * future types deliberately get no pill (null) rather than a blank chip.
     */
    public static function pillFor(string $type): ?string
    {
        return match ($type) {
            'class.joined.teacher' => 'Joined',
            'class.invite.accepted.teacher' => 'Invite',
            'assignment.completed.teacher' => 'Finished',
            default => null,
        };
    }

    /**
     * Mark a single notice read, then follow its link (an automation for the
     * inbox rows: tap the row -> reads it -> goes to the relevant page).
     */
    public function read(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markRead();

        return redirect($this->destination($notification) ?: route('teacher.dashboard'));
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['unread' => 0]);
        }

        return back()->with('toast', ['message' => 'All notifications marked as read.']);
    }

    /**
     * Resolve a synthetic inbox link to the real management page. The link is
     * also the dedupe key, so it carries the entity ids; known prefixes map to
     * routes, and anything else (an unknown or deleted entity) falls back to
     * null so the teacher still lands somewhere useful.
     */
    private function destination(Notification $notification): ?string
    {
        $link = (string) $notification->link;
        $parts = explode(':', $link);

        if (($parts[0] ?? null) === 'assignment-complete' && isset($parts[1])) {
            $assignment = Assignment::find((int) $parts[1]);

            return $assignment ? route('teacher.assignments.show', $assignment) : null;
        }

        if (in_array($parts[0] ?? null, ['class-joined', 'class-invite-accepted'], true) && isset($parts[1])) {
            $group = Group::find((int) $parts[1]);

            return $group ? route('teacher.classes.show', $group) : null;
        }

        return null;
    }
}
