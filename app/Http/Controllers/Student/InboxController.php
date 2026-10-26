<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\KeyTransaction;
use App\Models\Notification;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The student's notification inbox. Read-only convenience — a student who
 * never opens it still sees assignments via the hub "From your teacher" card.
 */
class InboxController extends Controller
{
    /**
     * The tab filters a student can browse. `assignments` covers every
     * assignment.* subtype and `invites` the class invites. Anything not on
     * the list falls back to `all`, so an unknown filter value never reaches
     * the query.
     */
    private const FILTERS = ['all', 'unread', 'assignments', 'invites'];

    /** Unread count for the header bell poll (every 30s). */
    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $request->user()->notifications()->unread()->count(),
        ]);
    }

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
            ->when($filter === 'assignments', fn ($q) => $q->where('type', 'like', 'assignment.%'))
            ->when($filter === 'invites', fn ($q) => $q->where('type', 'class.invite.teacher'));

        $base = $request->user()->notifications();

        return view('student.inbox', [
            'notifications' => $notifications->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                'all' => $base->count(),
                'unread' => (clone $base)->unread()->count(),
                'assignments' => (clone $base)->where('type', 'like', 'assignment.%')->count(),
                'invites' => (clone $base)->where('type', 'class.invite.teacher')->count(),
            ],
            'unreadCount' => (clone $base)->unread()->count(),
            'hasAny' => $base->exists(),
        ]);
    }

    /**
     * The nav bell's dropdown preview: the newest rows as an HTML fragment
     * plus the live unread count. Fetched when the panel opens, never at page
     * load, so the count query stays off the hot path and the list is fresh
     * however long the student has been on one page.
     *
     * Unread rows come first, then recency — the bell badge counts unread, so
     * the panel has to agree with it. Two small limited queries rather than one
     * ORDER BY on an expression keeps this portable and index-friendly.
     *
     * Rows are link-only: the two actionable types are decided on the inbox
     * page, and must NOT be marked read by a preview tap (see the partial).
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
            'html' => view('student.partials.inbox-preview', [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
            ])->render(),
            'unread' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification read, then follow its link (an automation
     * for the inbox list rows: tap the row -> reads it -> goes to the detail).
     * Links are resolved by {@see destinationFor()}: real URLs pass through,
     * synthetic hub/achievement keys map to their surface, and anything
     * unrecognized lands on the hub instead of a 404 (mirrors the teacher
     * inbox's destination fallback).
     */
    public function read(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markRead();

        return redirect($this->destinationFor($notification, $request->user()));
    }

    /**
     * Resolve a notification's link to a safe destination, guarding the raw
     * value from the two failure modes it can hit:
     *  - an assignment that was cancelled, deleted, or no longer scoped to the
     *    student (real URL, but dead from the student's seat → hub);
     *  - a synthetic key or an unknown future type (never a URL → hub, or its
     *    mapped surface for the known keys).
     */
    private function destinationFor(Notification $notification, User $user): string
    {
        $link = (string) $notification->link;

        if ($link === '') {
            return route('hub');
        }

        if (preg_match('#/assignments/(\d+)$#', $link, $matches)) {
            $assignment = Assignment::find((int) $matches[1]);

            if ($assignment === null || ! $assignment->isVisibleTo($user)) {
                return route('hub');
            }

            return $link;
        }

        return match (true) {
            str_starts_with($link, 'achievement:') => route('hub').'#badges',
            $link === 'reward:welcome-kit' => route('hub'),
            str_starts_with($link, 'class-invite:') => route('inbox'),
            str_starts_with($link, '/') => $link,
            default => route('hub'),
        };
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);

        // The bell panel posts this with Accept: application/json so it can
        // clear the badge and re-render in place; the inbox page's plain form
        // keeps the redirect + toast it always had.
        if ($request->expectsJson()) {
            return response()->json(['unread' => 0]);
        }

        return back()->with('toast', ['message' => 'All notifications marked as read.']);
    }

    /**
     * Claim the one-time starter reward. Pairs with the `reward.welcome_kit`
     * notification created at placement completion: ownership and type are
     * gate-kept, the ledger guard (`reason=welcome_kit`) stops a double grant
     * even if a ghost row survives, and the notification is consumed on claim
     * so a stale tab replaying the form gets a 404. Keys are granted through
     * RewardService under a user-row lock, so two near-simultaneous tabs can
     * never both pay out.
     */
    public function claim(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->type !== 'reward.welcome_kit') {
            abort(404);
        }

        $keys = (int) config('placement.welcome_kit.keys', 0);

        $claimed = DB::transaction(function () use ($request, $notification, $keys): bool {
            $alreadyPaid = KeyTransaction::query()
                ->where('user_id', $request->user()->id)
                ->where('reason', 'welcome_kit')
                ->exists();

            if ($alreadyPaid || $keys <= 0) {
                return false;
            }

            RewardService::grant($request->user(), $keys, 'welcome_kit');

            $notification->delete();

            return true;
        });

        if (! $claimed) {
            // Already paid out (or a zero-value config); clear the ticket and
            // land the student on the hub without double-charging.
            $notification->delete();

            return redirect(route('hub'));
        }

        return back()->with('toast', ['message' => 'Starter reward claimed! +'.$keys.' keys.']);
    }

    /**
     * The visual pill a row shows, keyed by notification type. Unknown or
     * future types deliberately get no pill (null) rather than a blank chip.
     */
    public static function pillFor(string $type): ?string
    {
        return match ($type) {
            'assignment.new' => 'New',
            'assignment.due_soon' => 'Due soon',
            'assignment.overdue' => 'Overdue',
            'assignment.updated' => 'Updated',
            'class.invite.teacher' => 'Invite',
            'reward.welcome_kit' => 'Reward',
            'achievement.title' => 'Title',
            'achievement.badge' => 'Badge',
            'report.resolved' => 'Report',
            'report.declined' => 'Report',
            default => null,
        };
    }
}
