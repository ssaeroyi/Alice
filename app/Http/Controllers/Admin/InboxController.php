<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StoryReport;
use App\Support\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The admin's live notification inbox. Notices arrive from student reports
 * (report.new) and system watchdog events (system.*). The unread JSON endpoint
 * powers the top-bar badge poll and also runs the cheap watchdog checks, so a
 * real admin who keeps an admin tab open is always told about mishaps.
 */
class InboxController extends Controller
{
    /**
     * The tab filters an admin can browse. `reports` covers every report.*
     * subtype and `system` the system.* watchdog alerts. Anything not on the
     * list falls back to `all`, so an unknown filter value never reaches the
     * query.
     */
    private const FILTERS = ['all', 'unread', 'reports', 'system'];

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
            ->when($filter === 'reports', fn ($q) => $q->where('type', 'report.new'))
            ->when($filter === 'system', fn ($q) => $q->where('type', 'like', 'system.%'));

        $base = $request->user()->notifications();

        return view('admin.inbox', [
            'notifications' => $notifications->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                'all' => $base->count(),
                'unread' => (clone $base)->unread()->count(),
                'reports' => (clone $base)->where('type', 'report.new')->count(),
                'system' => (clone $base)->where('type', 'like', 'system.%')->count(),
            ],
            'unreadCount' => (clone $base)->unread()->count(),
        ]);
    }

    /**
     * JSON badge endpoint for the live poller, available only to real admins
     * (the enclosing role:admin group already rejects preview sessions). Runs
     * the cheap watchdog transition checks on every poll — dedupe guarantees
     * they can never stack rows, only escalate once a day at most.
     */
    public function unread(): JsonResponse
    {
        NotificationService::watchdogAlerts();

        return response()->json([
            'unread' => request()->user()->notifications()->unread()->count(),
            'pendingReports' => StoryReport::query()->where('status', 'pending')->count(),
        ]);
    }

    /**
     * The nav bell's dropdown preview: the newest rows as an HTML fragment
     * plus the live unread count, fetched when the panel opens rather than at
     * page load. Unread rows come first, then recency, so the panel agrees
     * with the badge. Also runs the cheap watchdog checks so a fresh alert
     * appears the moment the panel opens.
     */
    public function preview(Request $request): JsonResponse
    {
        NotificationService::watchdogAlerts();

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
            'html' => view('admin.partials.inbox-preview', [
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
            'report.new' => 'Report',
            'system.maintenance' => 'Maintenance',
            'system.five_xx', 'system.queue', 'system.client_errors' => 'System',
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

        return redirect($this->destination($notification) ?: route('admin.dashboard'));
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['unread' => 0]);
        }

        return redirect()->route('admin.inbox')->with('toast', ['message' => 'All notifications marked as read.']);
    }

    /**
     * Admin notifications either carry a real URL (watchdog alerts) or a
     * synthetic `report-new:{id}` key (student reports, so no schema change is
     * needed to surface each individual report). A deleted entity degrades to
     * null so the admin still lands somewhere useful.
     */
    private function destination(Notification $notification): ?string
    {
        $link = (string) $notification->link;

        if (str_starts_with($link, 'http') || str_starts_with($link, '/')) {
            return $link;
        }

        if (str_starts_with($link, 'report-new:')) {
            return route('admin.reports.index');
        }

        return null;
    }
}
