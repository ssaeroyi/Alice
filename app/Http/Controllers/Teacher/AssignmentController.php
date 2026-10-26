<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentMark;
use App\Models\Group;
use App\Models\Story;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Support\AssignmentAudience;
use App\Support\Audit;
use App\Support\IntegritySignals;
use App\Support\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    /**
     * The assignment board: status-tabbed, paginated. Every assignment the
     * teacher has created lands under one of Active / Draft / Cancelled /
     * Finished, newest first, with per-tab counts and compliance.
     */
    public function index(Request $request): View
    {
        $tabs = ['active', 'draft', 'cancelled', 'archived'];
        $tab = in_array($request->query('status'), $tabs, true)
            ? $request->query('status')
            : 'active';

        // Optional class context for the breadcrumb: resolves to a class the
        // teacher owns when they arrive via "View all" from a class dashboard.
        $class = null;
        if ($classId = $request->query('class')) {
            $class = Group::query()
                ->where('teacher_id', auth()->id())
                ->whereKey($classId)
                ->first();
        }

        $q = trim((string) $request->query('q'));

        $owned = Assignment::query()
            ->with(['story'])
            ->where('teacher_id', auth()->id());

        $counts = (clone $owned)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $assignments = (clone $owned)
            ->when($q !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('title', 'like', '%'.$q.'%')
                ->orWhereHas('story', fn ($story) => $story->where('title', 'like', '%'.$q.'%'))))
            ->where('status', $tab)
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('teacher.assignments.index', [
            'assignments' => $assignments,
            'tabs' => $tabs,
            'tab' => $tab,
            'counts' => $counts,
            'class' => $class,
        ]);
    }

    /**
     * Manually retire an active/draft assignment into "Finished". Students keep
     * access to it under the collapsed "Past" section, and it drops out of the
     * active listings. Recorded in the audit log.
     */
    public function archive(Assignment $assignment): RedirectResponse
    {
        $this->authorize('archive', $assignment);

        $assignment->archive('manual');

        return back()->with('toast', ['message' => '"'.$assignment->title.'" was finished.']);
    }

    public function create(Request $request): View
    {
        $classes = $this->myClasses();

        $prefillClassId = $request->integer('class') ?: null;
        if ($prefillClassId !== null && ! $classes->contains('id', $prefillClassId)) {
            abort(403);
        }

        return view('teacher.assignments.form', [
            'assignment' => null,
            'prefillStoryId' => $request->integer('story_id') ?: null,
            'prefillClassId' => $prefillClassId,
            'stories' => Story::query()
                ->whereIn('status', [StoryStatus::Published, StoryStatus::Draft])
                ->orderBy('title')
                ->get(['id', 'title', 'reading_level', 'status', 'cover_image']),
            'classes' => $classes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Assignment::class);

        $data = $this->validated($request);

        // A live assignment can't ask students to hand work in on a day that's
        // already gone — reject a past due date when publishing it active now.
        if ($data['status'] === 'active' && ! empty($data['due_at']) && Carbon::parse($data['due_at'])->startOfDay()->lt(Carbon::today())) {
            return back()->withErrors(['due_at' => 'Due date can\'t be in the past for an active assignment.'])->withInput();
        }

        $assignment = Assignment::create([
            'teacher_id' => auth()->id(),
            'story_id' => $data['story_id'],
            'group_id' => $data['group_id'],
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'assigned_at' => $data['status'] === 'active' ? now() : null,
            'scope' => $data['scope'],
            'status' => $data['status'],
            'share_code' => Str::upper(Str::random(8)),
        ]);

        Audit::log('assignment.created', $assignment);

        // Publishing at creation time notifies the audience immediately.
        if ($data['status'] === 'active') {
            NotificationService::assignmentNew($assignment);
        }

        // Class-scoped assignments send the teacher back to that class, where the
        // new card appears; school-wide/individual ones stay on the board.
        $destination = $assignment->group_id
            ? route('teacher.classes.show', $assignment->group_id)
            : route('teacher.assignments.index');

        return redirect($destination)
            ->with('toast', ['message' => '"'.$assignment->title.'" was created.']);
    }

    public function show(Assignment $assignment, Request $request): View
    {
        $this->authorize('view', $assignment);

        $data = $this->rollupData($assignment, $request->query('q'));

        return view('teacher.assignments.rollup', [
            'assignment' => $assignment,
            ...$data,
        ]);
    }

    /**
     * Shared payload for the assignment detail page and the CSV export: the
     * audience (optionally narrowed by "q"), per-student quiz summaries (latest
     * attempt: percentage, raw "X of Y correct", pass flag) and the set of
     * teacher-marked student ids. Kept in one place so the page and the export
     * can never drift.
     */
    private function rollupData(Assignment $assignment, ?string $q = null): array
    {
        $assignment->load(['story', 'targets.user']);

        $audience = AssignmentAudience::resolve($assignment)
            ->when($q !== null && trim($q) !== '', function ($query) use ($q) {
                $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($q)).'%';

                $query->where(function ($query) use ($needle) {
                    $query->where('nickname', 'like', $needle)
                        ->orWhere('email', 'like', $needle)
                        ->orWhere('first_name', 'like', $needle)
                        ->orWhere('last_name', 'like', $needle);
                });
            })
            ->with('readingProgress')
            ->get();

        $audienceIds = $audience->pluck('id');

        $hasQuiz = $assignment->hasQuiz();
        $completion = $assignment->completionMapFor($audience);

        $quizSummaries = collect();
        if ($hasQuiz) {
            $latestAttempts = TestAttempt::where('story_id', $assignment->story_id)
                ->whereIn('user_id', $audienceIds)
                ->orderByDesc('created_at')
                ->get()
                ->unique('user_id')
                ->keyBy('user_id');

            $quizSummaries = $audience->mapWithKeys(function ($student) use ($latestAttempts, $completion) {
                $attempt = $latestAttempts->get($student->id);
                $correct = (int) ($attempt?->correct_count ?? 0);
                $total = $correct + (int) ($attempt?->wrong_count ?? 0);

                $items = collect($attempt?->answers_json['items'] ?? []);
                $pointsTotal = (int) $items->sum(fn ($it) => max((int) ($it['worth'] ?? 0), 0));
                $pointsEarned = (int) round($items->sum(fn ($it) => min(max((float) ($it['earned'] ?? 0), 0), max((int) ($it['worth'] ?? 0), 0))));

                return [$student->id => [
                    'score' => $attempt?->score_pct,
                    'raw' => $total > 0 ? $correct.' of '.$total : null,
                    'points' => $attempt !== null && $pointsTotal > 0 ? $pointsEarned.' of '.$pointsTotal : null,
                    'passed' => (bool) ($attempt?->passed ?? false),
                    'attempted' => $attempt !== null,
                    'completed' => $completion[$student->id]['done'] ?? false,
                ]];
            });
        }

        $marks = $assignment->marks()->with('teacher')->get()->keyBy('user_id');

        // Per-student data for the inspection drawer: full attempt history on
        // this story, the story-progress row, and the manual-mark (with the
        // teacher who placed it). All bulk-loaded by user id to avoid N+1.
        $allAttempts = collect();
        $storyProgress = collect();
        if ($assignment->story_id !== null) {
            $allAttempts = TestAttempt::where('story_id', $assignment->story_id)
                ->whereIn('user_id', $audienceIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('user_id');

            $storyProgress = StoryProgress::where('story_id', $assignment->story_id)
                ->whereIn('user_id', $audienceIds)
                ->get()
                ->keyBy('user_id');
        }

        $drawer = $audience->mapWithKeys(function ($student) use ($marks, $allAttempts, $storyProgress) {
            $attemptRows = ($allAttempts->get($student->id) ?? collect())->map(function ($attempt) {
                return [
                    'attempt' => $attempt,
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

            return [$student->id => [
                'progress' => $storyProgress->get($student->id),
                'attempts' => $attemptRows,
                'mark' => $marks->get($student->id),
            ]];
        });

        return [
            'audience' => $audience,
            'hasQuiz' => $hasQuiz,
            'quizSummaries' => $quizSummaries,
            'marks' => $marks,
            'markedIds' => $marks->keys(),
            'completion' => $completion,
            'drawer' => $drawer,
            'keyword' => $q,
        ];
    }

    /**
     * Teacher manually marks a student done (covers work the system can never
     * auto-record, e.g. a story read before the assignment went live). The mark
     * counts everywhere completion is read; it never fabricates read/quiz
     * evidence. Restricted to the audience and to the assignment owner.
     */
    public function markComplete(Assignment $assignment, Request $request): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $validated = $request->validate(['student_id' => ['required', 'integer']]);

        $student = AssignmentAudience::resolve($assignment)
            ->whereKey($validated['student_id'])
            ->first();

        abort_if($student === null, 422, "That student isn't in this assignment's audience.");

        AssignmentMark::firstOrCreate([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
        ], [
            'teacher_id' => $request->user()->id,
        ]);

        Audit::log('assignment.marked', $assignment, ['student_id' => $student->id]);

        return back()->with('toast', [
            'message' => ($student->nickname ?? strtok($student->email, '@')).' marked as done.',
        ]);
    }

    public function markRemove(Assignment $assignment, User $student): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $assignment->marks()->where('user_id', $student->id)->delete();

        Audit::log('assignment.mark_removed', $assignment, ['student_id' => $student->id]);

        return back()->with('toast', [
            'message' => ($student->nickname ?? strtok($student->email, '@')).' mark removed.',
        ]);
    }

    /**
     * CSV of the rollup (same data as the Students / Quiz tabs, honoring any
     * active "q" search). Openable by any teacher with view access.
     */
    public function export(Assignment $assignment, Request $request)
    {
        $this->authorize('view', $assignment);

        $data = $this->rollupData($assignment, $request->query('q'));
        $audience = $data['audience'];
        $quizSummaries = $data['quizSummaries'];
        $markedIds = $data['markedIds'];
        $completion = $data['completion'];

        Audit::log('assignment.exported', $assignment, ['rows' => $audience->count()]);

        $filename = 'assignment-'.$assignment->id.'-'.Str::slug($assignment->title).'.csv';

        return response()->streamDownload(function () use ($audience, $quizSummaries, $markedIds, $completion) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Student', 'Status', 'Quiz score', 'Percentage', 'Passed', 'Teacher marked']);

            foreach ($audience as $student) {
                $summary = $quizSummaries->get($student->id);
                $progress = $completion[$student->id] ?? ['read' => false, 'quiz' => false, 'completed' => false];
                $marked = $markedIds->contains($student->id);

                $status = $progress['completed']
                    ? 'Done'
                    : ($marked ? 'Done (teacher)' : ($progress['read'] || $progress['quiz'] ? 'In progress' : 'Not started'));

                fputcsv($handle, [
                    $student->nickname ?? strtok($student->email, '@'),
                    $status,
                    $summary && $summary['attempted'] ? ''.$summary['raw'] : '—',
                    $summary && $summary['attempted'] ? round((float) $summary['score']).'%' : '—',
                    $summary && $summary['attempted'] ? ($summary['passed'] ? 'Yes' : 'No') : '—',
                    $marked ? 'Yes' : '—',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function edit(Assignment $assignment): View
    {
        $this->authorize('update', $assignment);

        return view('teacher.assignments.form', [
            'assignment' => $assignment->load('targets'),
            'prefillStoryId' => null,
            'prefillClassId' => null,
            'stories' => Story::query()
                ->whereIn('status', [StoryStatus::Published, StoryStatus::Draft])
                ->orderBy('title')
                ->get(['id', 'title', 'reading_level', 'status', 'cover_image']),
            'classes' => $this->myClasses(),
        ]);
    }

    public function update(Assignment $assignment, Request $request): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $data = $this->validated($request);

        $wasActive = $assignment->status === 'active';
        $meaningfulChanged = $wasActive && (
            $assignment->title !== $data['title']
            || $assignment->instructions !== $data['instructions']
            || ($assignment->due_at?->toDateTimeString() ?? null) !== ($data['due_at'] ?? null)
            || $assignment->story_id !== ($data['story_id'] ?? null)
        );

        $assignment->update([
            'story_id' => $data['story_id'],
            'group_id' => $data['group_id'],
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'scope' => $data['scope'],
            'status' => $data['status'],
        ]);

        Audit::log('assignment.updated', $assignment);

        // Notify the (possibly re-scoped) audience about meaningful changes
        // while the assignment is live, coalesced to one notice per day.
        if ($meaningfulChanged) {
            NotificationService::assignmentUpdated($assignment);
        }

        return redirect()
            ->route('teacher.assignments.show', $assignment)
            ->with('toast', ['message' => '"'.$assignment->title.'" was updated.']);
    }

    public function publish(Assignment $assignment): RedirectResponse
    {
        $this->authorize('publish', $assignment);

        $wasDraft = $assignment->status === 'draft';

        $assignment->update([
            'status' => 'active',
            // Stamp the go-live moment on first activation only, so re-publishing
            // does not extend the "read since" window for existing work.
            'assigned_at' => $assignment->assigned_at ?? now(),
        ]);

        Audit::log('assignment.published', $assignment);

        if ($wasDraft) {
            NotificationService::assignmentNew($assignment);
        }

        return back()->with('toast', ['message' => '"'.$assignment->title.'" is now active and visible to '.(new AssignmentAudience)->label($assignment).'.']);
    }

    /**
     * Manually push a "new assignment" notice to the current audience. Useful
     * for backfilling an older active assignment or nudging students who
     * never saw it. Idempotent: the service de-dupes per assignment+student.
     */
    public function notify(Assignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $sent = NotificationService::assignmentNew($assignment);

        return back()->with('toast', [
            'message' => $sent > 0
                ? 'Notification sent to '.$sent.' student'.($sent === 1 ? '' : 's').'.'
                : 'No new notification needed — your students already have it.',
        ]);
    }

    public function cancel(Assignment $assignment): RedirectResponse
    {
        $this->authorize('cancel', $assignment);

        $assignment->update(['status' => 'cancelled']);

        Audit::log('assignment.cancelled', $assignment);

        return back()->with('toast', ['message' => '"'.$assignment->title.'" was cancelled.']);
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        $assignment->delete();

        Audit::log('assignment.deleted', $assignment);

        return redirect()
            ->route('teacher.assignments.index')
            ->with('toast', ['message' => '"'.$assignment->title.'" was deleted.']);
    }

    /**
     * Shared assignment link handler: increment the click counter and hand the
     * student off to the assignment detail page (sender, due date, instructions,
     * completion state, Start-reading CTA). Without a story it lands on the hub.
     */
    public function openShare(string $code): RedirectResponse
    {
        $assignment = Assignment::where('share_code', $code)
            ->where('status', 'active')
            ->firstOrFail();

        $assignment->increment('clicks_count');

        Audit::log('assignment.share_click', $assignment, [
            'user_id' => auth()->id(),
            'code' => $code,
        ]);

        if ($assignment->story_id && $assignment->story) {
            return redirect()->route('student.assignments.show', $assignment);
        }

        return redirect()->route('hub');
    }

    /**
     * The teacher's classes for the assignment "A class" scope picker.
     */
    private function myClasses()
    {
        return Group::query()
            ->withCount('members')
            ->where('teacher_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Validate and normalize the assignment payload (now class-scope only).
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'story_id' => ['required', 'integer', 'exists:stories,id'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'due_at' => ['nullable', 'date'],
            'scope' => ['required', 'string', 'in:group'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'status' => ['sometimes', 'string', 'in:draft,active'],
        ]);

        $data['status'] = $data['status'] ?? 'draft';

        abort_unless(
            $data['group_id'] !== null
            && Group::whereKey($data['group_id'])->where('teacher_id', auth()->id())->exists(),
            422
        );

        return $data;
    }
}
