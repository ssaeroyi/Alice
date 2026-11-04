<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Role;
use App\Enums\StoryProgressStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Support\Audit;
use App\Support\IntegritySignals;
use App\Support\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A teacher's "Classes" area: create classes, get a join code, manage members.
 * Class membership is the self-serve enrollment that lets every student see
 * their class, teacher and classmates (and powers class-scoped assignments).
 */
class ClassController extends Controller
{
    public function index(): View
    {
        $groups = Group::query()
            ->withCount(['members', 'assignments'])
            ->where('teacher_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('teacher.classes.index', ['groups' => $groups]);
    }

    public function create(): View
    {
        return view('teacher.classes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group = Group::create([
            'teacher_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'join_code' => Group::makeJoinCode(),
        ]);

        Audit::log('group.created', $group);

        return redirect()
            ->route('teacher.classes.show', $group)
            ->with('toast', [
                'message' => 'Class "'.$group->name.'" created. Share its join code to add students.',
            ]);
    }

    public function show(Group $group, Request $request): View
    {
        $this->authorizeOwner($group);

        $group->load([
            'members' => fn ($q) => $q->realAccounts()->with('readingProgress'),
            'assignments' => fn ($q) => $q->with('story')->orderByDesc('created_at'),
        ]);

        $members = $group->members->sortBy(fn (User $student) => Str::of(
            trim(($student->last_name ?? '').' '.($student->first_name ?? ''))
        )->trim()->lower()->__toString())->values();

        $rosterStats = $this->rosterStats($group, $members);

        // Drawer payloads, bulk-loaded once: each member's last five attempts
        // across any story (attempt ids are pre-sorted by recency per member).
        $attemptGroups = TestAttempt::query()
            ->whereIn('user_id', $members->pluck('id')->all())
            ->with('story')
            ->orderByDesc('created_at')
            ->limit(max(count($members) * 5, 100))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->take(5));

        $detail = function (User $student) use ($rosterStats, $attemptGroups) {
            $rows = ($attemptGroups->get($student->id) ?? collect())->map(function ($attempt) {
                return [
                    'attempt' => $attempt,
                    'story' => $attempt->story?->title,
                    'score' => $attempt->score_pct,
                    'passed' => (bool) $attempt->passed,
                    'correct' => (int) ($attempt->correct_count ?? 0),
                    'wrong' => (int) ($attempt->wrong_count ?? 0),
                    'total' => (int) ($attempt->correct_count ?? 0) + (int) ($attempt->wrong_count ?? 0),
                    'time' => (int) ($attempt->time_spent_seconds ?? 0),
                    'hints' => (int) ($attempt->hint_tokens_used ?? 0),
                    'xp' => (int) ($attempt->xp_awarded ?? 0),
                    'at' => $attempt->created_at,
                    'notes' => IntegritySignals::hasFlags($attempt->attempt_flags ?? [])
                        ? IntegritySignals::reviewNotes($attempt->attempt_flags ?? [])
                        : [],
                ];
            })->values();

            return [
                'progress' => null,
                'finished_count' => $rosterStats[$student->id]['stories_finished'] ?? 0,
                'activity_at' => $rosterStats[$student->id]['activity_at'] ?? null,
                'attempts' => $rows,
                'mark' => null,
            ];
        };

        $sort = $request->query('sort');

        if ($sort !== null && $sort !== 'name') {
            $members = match ($sort) {
                'score' => $members->sort(function ($a, $b) use ($rosterStats) {
                    [$sa, $sb] = [$rosterStats[$a->id]['avg_score'] ?? -1, $rosterStats[$b->id]['avg_score'] ?? -1];
                    if ($sa !== $sb) return $sb <=> $sa;
                    return $a->id <=> $b->id;
                })->values(),
                'stories' => $members->sort(function ($a, $b) use ($rosterStats) {
                    [$fa, $fb] = [$rosterStats[$a->id]['stories_finished'] ?? 0, $rosterStats[$b->id]['stories_finished'] ?? 0];
                    if ($fa !== $fb) return $fb <=> $fa;
                    return $a->id <=> $b->id;
                })->values(),
                default => $members->sort(function ($a, $b) use ($rosterStats) {
                    [$ta, $tb] = [$rosterStats[$a->id]['activity_ts'] ?? 0, $rosterStats[$b->id]['activity_ts'] ?? 0];
                    if ($ta !== $tb) return $tb <=> $ta;
                    return $a->id <=> $b->id;
                })->values(),
            };
        }

        // Assignments keep submit-order, with each grabbing the class members
        // once for its completion math.
        $assignmentRows = $group->assignments->map(function ($assignment) use ($members) {
            $completion = $assignment->completionMapFor($members);
            $completed = collect($completion)->filter(fn ($row) => $row['done'])->count();
            $started = collect($completion)->filter(fn ($row) => $row['read'] || $row['quiz'])->count();
            $audienceCount = max(1, $assignment->completionMapForAudienceCount ?? $members->count());

            return [
                'assignment' => $assignment,
                'completed' => $completed,
                'started' => $started,
                'audience' => $members->count(),
                'pct' => (int) round(100 * $completed / max(1, $members->count())),
            ];
        });

        return view('teacher.classes.show', [
            'group' => $group,
            'members' => $members,
            'roster' => $rosterStats,
            // The class page previews the three newest assignments; the full
            // list lives under Class & Assignments with its tabs.
            'assignmentRows' => $assignmentRows->take(3),
            'tiles' => $this->classTiles($group, $members, $rosterStats, $assignmentRows),
            'detail' => $detail,
            'sortParam' => in_array($sort, ['name', 'score', 'stories', 'activity'], true) ? $sort : 'name',
        ]);
    }

    /**
     * Per-member truth, bulk-loaded: latest attempt per member (any story)
     * gives the accuracy dial; the completed story count gives progress;
     * presence stays the one heart-beat the roster already trusts.
     *
     * @return array<int, array>
     */
    private function rosterStats(Group $group, Collection $members): array
    {
        $ids = $members->pluck('id')->all();

        $latestAttempts = TestAttempt::query()
            ->whereIn('user_id', $ids)
            ->orderByDesc('created_at')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $finished = StoryProgress::query()
            ->whereIn('user_id', $ids)
            ->where('status', StoryProgressStatus::Completed)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $members->mapWithKeys(fn (User $student) => [
            $student->id => [
                'best_at' => $latestAttempts->get($student->id)?->created_at,
                'best_story_id' => $latestAttempts->get($student->id)?->story_id,
                'score' => $latestAttempts->get($student->id)?->score_pct,
                'passed' => (bool) ($latestAttempts->get($student->id)?->passed ?? false),
                'avg_score' => $latestAttempts->get($student->id)?->score_pct,
                'stories_finished' => (int) ($finished[$student->id] ?? 0),
                'activity_ts' => $student->lastActiveAt()?->timestamp ?? 0,
                'activity_at' => $student->lastActiveAt(),
            ],
        ])->all();
    }

    /**
     * The class hero: how the class is doing against its own work.
     *
     * @return array
     */
    private function classTiles(Group $group, Collection $members, array $roster, $assignmentRows): array
    {
        $activeAssignments = $assignmentRows->filter(fn ($row) => $row['assignment']->status === 'active');
        $completionPct = $assignmentRows->isNotEmpty()
            ? (int) round($assignmentRows->avg(fn ($row) => $row['pct']))
            : null;

        $scores = collect($roster)->filter(fn ($row) => $row['score'] !== null);
        $avgScore = $scores->isNotEmpty() ? (int) round($scores->avg('score')) : null;

        $storiesFinished = collect($roster)->sum(fn ($row) => $row['stories_finished']);

        $activeToday = collect($roster)
            ->filter(fn ($row) => $row['activity_ts'] > now()->timestamp - 86400)
            ->count();

        return [
            'completion_pct' => $completionPct,
            'avg_score' => $avgScore,
            'stories_finished' => $storiesFinished,
            'active_today' => $activeToday,
            'assignments_active' => $activeAssignments->count(),
            'assignments_total' => $assignmentRows->count(),
            'member_count' => $members->count(),
        ];
    }

    /** Editable class details (description) on the teacher's class page. */
    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeOwner($group);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group->update(['description' => $validated['description'] ?? null]);

        return back()->with('toast', ['message' => 'Class details saved.']);
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorizeOwner($group);

        Audit::log('group.deleted', $group);
        $group->delete();

        return redirect()
            ->route('teacher.classes.index')
            ->with('toast', ['message' => 'Class "'.$group->name.'" was removed.']);
    }

    /**
     * Remove a student from a class directly (teacher-managed).
     */
    public function removeMember(Group $group, User $user): RedirectResponse
    {
        $this->authorizeOwner($group);

        $group->removeMember($user);

        return back()->with('toast', ['message' => $user->first_name.' was removed from '.$group->name.'.']);
    }

    /**
     * Roster picker of students who can still be invited to this class.
     */
    public function membersCreate(Group $group): View
    {
        $this->authorizeOwner($group);

        $students = $this->allStudents();
        $availableStudents = $students->filter(
            fn (User $student) => ! $group->isMember($student)
        );

        return view('teacher.classes.members-create', [
            'group' => $group,
            'availableStudents' => $availableStudents,
            'anyStudentsExist' => $students->isNotEmpty(),
        ]);
    }

    /**
     * Send class-invite notifications to the selected students.
     */
    public function membersInvite(Group $group, Request $request): RedirectResponse
    {
        $this->authorizeOwner($group);

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $members = $group->members()->pluck('users.id')->all();
        $userIds = array_values(array_diff($validated['student_ids'], $members));

        $sent = NotificationService::classInvite($group, $userIds);

        return redirect()
            ->route('teacher.classes.show', $group)
            ->with('toast', [
                'message' => $sent > 0
                    ? ($sent === 1 ? '1 student invitation sent.' : $sent.' student invitations sent.')
                    : 'Those students are already in this class or invited.',
            ]);
    }

    private function authorizeOwner(Group $group): void
    {
        abort_unless($group->teacher_id === auth()->id(), 403);
    }

    /**
     * Real registered students only (no scratch/demo/test accounts), for
     * explicitly adding to a class.
     *
     * @return Collection<int, User>
     */
    private function allStudents()
    {
        return User::query()
            ->where('role', Role::Student)
            ->realAccounts()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'nickname', 'email', 'avatar']);
    }
}
