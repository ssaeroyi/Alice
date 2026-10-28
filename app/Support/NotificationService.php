<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\ClientError;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Story;
use App\Models\StoryReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Central dispatch point for in-app notifications. Kept deliberately thin so
 * future notification types (system notices) can plug in without touching the
 * inbox UI. Assignment notices are batched (one row per student) and
 * de-duplicated so re-publishing or a repeated scheduler pass can never create
 * duplicates. Student achievement rows (rank promotions, badge unlocks) plug in
 * through the same (type, link) dedupe; they stay informational — badges never
 * move keys, and title-promotion keys are granted by TitleRewardService before
 * the row is written.
 */
class NotificationService
{
    private function __construct() {}

    /**
     * Dispatch an "assignment.new" ping to the assignment's audience.
     * Resolves the audience at call time and refuses to create a duplicate for
     * the same assignment + student, so a re-publish can't double-notify.
     */
    public static function assignmentNew(Assignment $assignment): int
    {
        if ($assignment->story_id === null) {
            return 0;
        }

        $userIds = AssignmentAudience::resolve($assignment)->pluck('id')->all();

        return self::dispatchForUsers($assignment, 'assignment.new', $userIds);
    }

    /**
     * Teacher-triggered "Remind class": the same due-soon-styled ping with a
     * 7-day cooldown so pressing it again later actually re-reminds.
     * Completed students never get it.
     */
    public static function assignmentRemind(Assignment $assignment): int
    {
        if ($assignment->story_id === null || $assignment->status !== 'active') {
            return 0;
        }

        $audience = AssignmentAudience::resolve($assignment)->get();
        $completion = $assignment->completionMapFor($audience);

        $userIds = $audience
            ->reject(fn (User $student) => $completion[(int) $student->id]['done'] ?? false)
            ->pluck('id')
            ->all();

        $existing = Notification::query()
            ->whereIn('user_id', $userIds)
            ->where('type', 'assignment.reminder')
            ->where('link', self::linkFor($assignment))
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('user_id')
            ->all();

        $userIds = array_values(array_diff(array_unique($userIds), $existing));

        return self::dispatchForUsers($assignment, 'assignment.reminder', $userIds);
    }

    /**
     * Dispatch a due-soon or overdue reminder, skipping students who already
     * completed the assignment.
     *
     * @param  'assignment.due_soon'|'assignment.overdue'  $type
     */
    public static function reminder(Assignment $assignment, string $type): int
    {
        if ($assignment->story_id === null || $assignment->status !== 'active') {
            return 0;
        }

        $audience = AssignmentAudience::resolve($assignment)->get();
        $completion = $assignment->completionMapFor($audience);

        $userIds = $audience
            ->reject(fn (User $student) => $completion[(int) $student->id]['done'] ?? false)
            ->pluck('id')
            ->all();

        return self::dispatchForUsers($assignment, $type, $userIds);
    }

    /**
     * Dispatch an "updated" ping to the current audience (post-scratch filter).
     * Coalesces: only one "updated" notice per assignment per day.
     */
    public static function assignmentUpdated(Assignment $assignment): int
    {
        if ($assignment->story_id === null || $assignment->status !== 'active') {
            return 0;
        }

        $userIds = AssignmentAudience::resolve($assignment)->pluck('id')->all();
        $today = now()->startOfDay();

        // Which audience members already got today's "updated" notice — one
        // IN() scan instead of an exists() per student.
        $existing = Notification::query()
            ->whereIn('user_id', $userIds)
            ->where('type', 'assignment.updated')
            ->where('link', self::linkFor($assignment))
            ->where('created_at', '>=', $today)
            ->pluck('user_id')
            ->all();

        $userIds = array_values(array_diff(array_unique($userIds), $existing));

        return self::dispatchForUsers($assignment, 'assignment.updated', $userIds);
    }

    /**
     * Mark the student's own assignment notices read once they complete the
     * work behind them. The bell dot is `notifications()->unread()->count()`
     * and completing an assignment never opened those rows, so without this a
     * finished assignment would leave its "New assignment" ping stuck in the
     * badge until the student manually visits the inbox. Only notices for
     * assignments on this story that are genuinely complete are cleared, so a
     * class invite or an unrelated story's pending notice stays unread.
     */
    public static function markCompletedAssignmentsRead(User $student, int $storyId): void
    {
        $assignments = Assignment::query()
            ->where('story_id', $storyId)
            ->where('status', 'active')
            ->get();

        $done = Assignment::completedStatusForStudent($student, $assignments);

        $assignments = $assignments
            ->filter(fn (Assignment $assignment) => $done[$assignment->id] ?? false);

        if ($assignments->isEmpty()) {
            return;
        }

        Notification::query()
            ->where('user_id', $student->getKey())
            ->where('type', 'like', 'assignment.%')
            ->whereNull('read_at')
            ->whereIn('link', $assignments->map(fn (Assignment $a) => self::linkFor($a))->all())
            ->update(['read_at' => now()]);
    }

    /**
     * Dispatch the one-time starter reward notification for a fresh placement
     * graduate. The keys themselves are never granted here — the row is just a
     * claimable ticket: CLAIM on the inbox/hub calls InboxController::claim,
     * which pays the keys and consumes the notification. Deduped on the
     * synthetic link so a re-run of placement completion can never stack rows.
     */
    public static function welcomeKitReward(User $user): int
    {
        $keys = (int) config('placement.welcome_kit.keys', 0);

        if ($keys <= 0) {
            return 0;
        }

        return self::dispatchSingle(
            (int) $user->id,
            'reward.welcome_kit',
            'Starter reward ready',
            'This is your welcome reward. It holds '.$keys.' keys. Tap Claim to add them to your balance.',
            'reward:welcome-kit',
        );
    }

    /**
     * Record a title-ladder promotion as an inbox row so the student can
     * revisit the achievement and be steered to the badge shelf. Purely
     * informational: the keys and the rank badge are granted by
     * TitleRewardService / BadgeService, so this row only makes the event
     * visible later. Deduped on the synthetic `achievement:title:{tier}` link,
     * so a replay settle or a repeated summary load can never stack a second
     * row for the same tier.
     */
    public static function rankPromoted(User $user, int $tier, string $title, int $keys): int
    {
        $tally = $keys === 1 ? '1 key' : $keys.' keys';

        return self::dispatchSingle(
            (int) $user->id,
            'achievement.title',
            'New title reached',
            'This is a rank-up alert. You reached '.$title.' and earned '.$tally.'.',
            'achievement:title:'.$tier,
        );
    }

    /**
     * Record a newly unlocked badge as an inbox row. Badges are trophies that
     * never move keys or points, so the row simply parks the student at the
     * badge shelf. Deduped on the synthetic `achievement:badge:{id}` link so a
     * replay of the awarding settle can never stack duplicates. Rank badges are
     * skipped by the caller (BadgeService::award) because the title promotion
     * carries its own row.
     */
    public static function badgeUnlocked(User $user, int $badgeId, string $badgeName): int
    {
        return self::dispatchSingle(
            (int) $user->id,
            'achievement.badge',
            'Badge unlocked',
            'This is a badge alert. You unlocked the '.$badgeName.' badge. See it on your Badges page.',
            'achievement:badge:'.$badgeId,
        );
    }

    /**
     * Send a "join this class" invite to a set of students. The group is
     * encoded into the notification's link (`class-invite:{group_id}`) so the
     * student's Accept / Not now actions can resolve it without schema changes.
     * Students already in the class are skipped.
     *
     * @param  array<int, int>  $userIds
     */
    public static function classInvite(Group $group, array $userIds): int
    {
        $link = 'class-invite:'.$group->id;
        $now = now();
        $rows = [];
        $userIds = array_values(array_unique($userIds));
        $memberIds = array_flip($group->members()->pluck('users.id')->all());

        // Who already holds an invite (or is a member) — resolved in one
        // IN() scan instead of one exists() per invitee.
        $existing = array_flip(Notification::query()
            ->whereIn('user_id', $userIds)
            ->where('type', 'class.invite.teacher')
            ->where('link', $link)
            ->pluck('user_id')
            ->all());

        foreach ($userIds as $userId) {
            if (isset($memberIds[$userId]) || isset($existing[$userId])) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'type' => 'class.invite.teacher',
                'title' => 'Class invite: '.$group->name,
                'body' => 'This is a class invite from '.($group->teacher?->name ?? 'your teacher').'. Accept it to join.',
                'link' => $link,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('notifications')->insert($rows);

        return count($rows);
    }

    /**
     * Notify a group's teacher that a student joined by code. The union of
     * entry paths (code, invite accept) always funnels through Group::addMember,
     * so the caller only fires this when addMember actually enrolled someone new
     * (idempotent re-joins never re-notify).
     */
    public static function classJoined(Group $group, User $student): int
    {
        if ($group->teacher_id === null) {
            return 0;
        }

        return self::dispatchSingle(
            (int) $group->teacher_id,
            'class.joined.teacher',
            'New student in '.$group->name,
            'This is a class update. '.self::studentName($student).' joined your class.',
            'class-joined:'.$group->id.':'.$student->id,
        );
    }

    /**
     * Notify the teacher that a student accepted their class invite. Fires in
     * ClassController::decide for the accept branch only — declines stay quiet.
     */
    public static function classInviteAccepted(Group $group, User $student): int
    {
        if ($group->teacher_id === null) {
            return 0;
        }

        return self::dispatchSingle(
            (int) $group->teacher_id,
            'class.invite.accepted.teacher',
            'Invite accepted',
            'This is a class update. '.self::studentName($student).' accepted your invitation to "'.$group->name.'".',
            'class-invite-accepted:'.$group->id.':'.$student->id,
        );
    }

    /**
     * Notify every teacher who has an active, group-scoped assignment on the
     * story that this student just completed it. Uses the SAME completion
     * semantics as the rollup (Assignment::completedFor), so a teacher is only
     * told about work the dashboard would also count. Dedupe (type + link)
     * guarantees exactly one notice per student + assignment, even across re-
     * reads and re-submits. Scratch preview students never reach a teacher.
     */
    public static function studentCompletedStory(User $student, int $storyId): int
    {
        if (ViewAs::isScratchTarget($student)) {
            return 0;
        }

        $assignments = Assignment::query()
            ->where('story_id', $storyId)
            ->where('status', 'active')
            ->whereNotNull('group_id')
            ->whereHas('group', fn ($q) => $q->whereHas(
                'members',
                fn ($m) => $m->whereKey($student->getKey()),
            ))
            ->get();

        $notified = 0;

        if ($assignments->isNotEmpty()) {
            $done = Assignment::completedStatusForStudent($student, $assignments);

            foreach ($assignments as $assignment) {
                if ($assignment->teacher_id === null || ! ($done[$assignment->id] ?? false)) {
                    continue;
                }

                $notified += self::dispatchSingle(
                    (int) $assignment->teacher_id,
                    'assignment.completed.teacher',
                    'Assignment finished: '.$assignment->title,
                    'This is a completion notice. '.self::studentName($student).' finished this assignment.',
                    'assignment-complete:'.$assignment->id.':'.$student->id,
                );
            }
        }

        return $notified;
    }

    /**
     * Insert a single-recipient notification, honoring the same (type, link)
     * dedupe as the batched student notices so a repeated event can never
     * stack the teacher's inbox.
     */
    private static function dispatchSingle(int $userId, string $type, string $title, string $body, string $link): int
    {
        $exists = Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('link', $link)
            ->exists();

        if ($exists) {
            return 0;
        }

        $now = now();

        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 1;
    }

    private static function studentName(User $student): string
    {
        return $student->nickname ?: strtok((string) $student->email, '@');
    }

    /**
     * Batch-insert a notice for a set of students, honoring the per-notice
     * dedupe (same type + link + user already exists -> skip).
     */
    private static function dispatchForUsers(Assignment $assignment, string $type, array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        $userIds = array_values(array_unique($userIds));

        $link = self::linkFor($assignment);
        $now = now();

        // Which audience members already hold this (type, link) notice —
        // one IN() scan instead of one exists() per student.
        $existing = Notification::query()
            ->whereIn('user_id', $userIds)
            ->where('type', $type)
            ->where('link', $link)
            ->pluck('user_id')
            ->flip();

        $rows = [];
        foreach ($userIds as $userId) {
            if ($existing->has($userId)) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => self::titleFor($assignment, $type),
                'body' => self::bodyFor($assignment, $type),
                'link' => $link,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('notifications')->insert($rows);

        return count($rows);
    }

    private static function linkFor(Assignment $assignment): string
    {
        return route('student.assignments.show', $assignment);
    }

    private static function titleFor(Assignment $assignment, string $type): string
    {
        return match ($type) {
            'assignment.new' => 'New assignment: '.$assignment->title,
            'assignment.due_soon' => 'Due soon: '.$assignment->title,
            'assignment.overdue' => 'Overdue: '.$assignment->title,
            'assignment.updated' => 'Assignment updated: '.$assignment->title,
            'assignment.reminder' => 'Reminder: '.$assignment->title,
            default => $assignment->title,
        };
    }

    private static function bodyFor(Assignment $assignment, string $type): string
    {
        return match ($type) {
            'assignment.due_soon' => 'This is a due reminder. It is due '
                .($assignment->due_at ? $assignment->due_at->diffForHumans() : 'soon')
                .'. Finish it before the deadline.',
            'assignment.overdue' => 'This is an overdue notice. The due date has passed. You can still finish it.',
            'assignment.updated' => 'This is an update notice. The teacher changed this assignment. Open it to see what changed.',
            'assignment.reminder' => 'A friendly reminder from your teacher. '
                .($assignment->due_at ? 'It is due '.$assignment->due_at->diffForHumans().'.' : 'No due date on this one.')
                .' Finish the reading'.($assignment->hasQuiz() ? ' and take the quiz' : '').' to mark it done.',
            default => 'This is a new assignment. Read '.($assignment->story?->title ?? 'the story').' and take the quiz to finish.',
        };
    }

    /**
     * Ping every real admin that a student reported an issue. One row per
     * report (dedupe key = type + report id), so two reports never collapse
     * into a single notice. Scratch preview reporters are skipped so a "view
     * as" session can't flood the admin inbox.
     */
    public static function reportCreated(StoryReport $report): int
    {
        $reporter = $report->user;

        if ($reporter === null || ViewAs::isScratchTarget($reporter)) {
            return 0;
        }

        $category = match ($report->category) {
            'typo' => 'Typo',
            'content' => 'Content',
            default => 'Other',
        };

        return self::dispatchToAdmins(
            'report.new',
            'New report: '.$category,
            'This is a student report. '.self::studentName($reporter).' reported an issue in "'.($report->story?->title ?? 'a story').'".',
            'report-new:'.$report->id,
        );
    }

    /**
     * Tell the reporting student their report was reviewed and resolved. The
     * link is the story URL so the student's inbox follow-through lands on the
     * fixed content. Raw link passthrough in the student InboxController means
     * no student-side resolution logic is needed.
     */
    public static function reportResolved(StoryReport $report): int
    {
        $reporter = $report->user;
        $story = $report->story_id !== null ? Story::find($report->story_id) : null;

        if ($reporter === null || ViewAs::isScratchTarget($reporter) || $story === null) {
            return 0;
        }

        return self::dispatchSingle(
            (int) $reporter->id,
            'report.resolved',
            'Report resolved',
            'This is a report update. An admin fixed the issue in "'.$story->title.'".',
            route('story.preview', $story),
        );
    }

    /**
     * Tell the reporting student their report was removed without action.
     * Honest loop-closure: the school can still see the action in the audit
     * log, so nothing is silently dropped.
     */
    public static function reportDeleted(StoryReport $report): int
    {
        $reporter = $report->user;
        $story = $report->story_id !== null ? Story::find($report->story_id) : null;

        if ($reporter === null || ViewAs::isScratchTarget($reporter) || $story === null) {
            return 0;
        }

        return self::dispatchSingle(
            (int) $reporter->id,
            'report.declined',
            'Report closed',
            'This is a report update. An admin closed your report about "'.$story->title.'" with no changes. You can submit a new report from the reader.',
            route('story.preview', $story),
        );
    }

    /**
     * Instant admin alert when maintenance mode flips. Intentionally NOT
     * deduped further than the (type, link) pair — the link carries a unique
     * timestamp, so every toggle produces exactly one fresh notification.
     */
    public static function maintenanceToggled(bool $on): int
    {
        return self::dispatchToAdmins(
            'system.maintenance',
            $on ? 'Maintenance mode is ON' : 'Maintenance mode is OFF',
            $on
                ? 'This is a site status change. Maintenance mode is on. Visitors see the maintenance page. The admin panel stays reachable.'
                : 'This is a site status change. Maintenance mode is off. The site is live again.',
            route('admin.control').'?mt='.now()->timestamp,
        );
    }

    /**
     * Cheap transition-based system checks for the poll heartbeat. Each check
     * resolves its own per-day column in the link, so a persistent problem
     * notifies once per day (not per poll), and a resolved-then-recurs signal
     * notifies again on a new day. All checks are defensive and never throw.
     */
    public static function watchdogAlerts(): int
    {
        $notified = 0;

        // 5xx spike: ten or more HTTP 5xx responses in the last 24 hours.
        $traffic = Monitor::traffic(24);
        if (($traffic['five_xx'] ?? 0) >= 10) {
            $notified += self::dispatchToAdmins(
                'system.five_xx',
                'Server error spike',
                'This is a server error alert. '.$traffic['five_xx'].' requests failed in the last 24 hours. Open Server errors on the Logs page.',
                route('admin.logs.index', ['tab' => 'server', 'status' => '5xx', 'preset' => 'last_24h']),
            );
        }

        // Client-error build-up: 25+ browser-side errors reported in 24 hours.
        $clientErrors = ClientError::where('created_at', '>=', now()->subHours(24))->count();
        if ($clientErrors >= 25) {
            $notified += self::dispatchToAdmins(
                'system.client_errors',
                'Client error build-up',
                'This is a browser error alert. '.$clientErrors.' errors were reported in the last 24 hours. Review them on the Logs page.',
                route('admin.logs.index', ['tab' => 'browser', 'preset' => 'last_24h']),
            );
        }

        return $notified;
    }

    /**
     * Fan a notice out to every real admin (never a scratch account — admins
     * are never subject to view-as). Honors the same (type, link) dedupe as
     * single-recipient notices so repeated events cannot stack rows.
     */
    public static function dispatchToAdmins(string $type, string $title, string $body, string $link, bool $dedupe = true): int
    {
        $adminIds = array_values(array_unique(User::where('role', Role::Admin)->pluck('id')->all()));

        if ($adminIds === []) {
            return 0;
        }

        $now = now();
        $rows = [];

        // Dedupe against every admin's existing (type, link) row in one IN()
        // scan.
        $existing = $dedupe
            ? Notification::query()
                ->whereIn('user_id', $adminIds)
                ->where('type', $type)
                ->where('link', $link)
                ->pluck('user_id')
                ->flip()
            : collect();

        foreach ($adminIds as $adminId) {
            if ($dedupe && $existing->has($adminId)) {
                continue;
            }

            $rows[] = [
                'user_id' => $adminId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('notifications')->insert($rows);

        return count($rows);
    }
}
