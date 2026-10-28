<?php

namespace App\Models;

use App\Enums\StoryProgressStatus;
use App\Support\AssignmentAudience;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Fillable(['teacher_id', 'story_id', 'group_id', 'title', 'instructions', 'due_at', 'assigned_at', 'finished_at', 'scope', 'grade_level', 'section', 'status', 'share_code'])]
class Assignment extends Model
{
    use SoftDeletes;

    /**
     * The one status vocabulary for student rows everywhere: teacher rollup,
     * student classwork widget, and the student assignment page all read
     * these labels and pill classes so the three can never disagree.
     *
     * @var array<string, array{label: string, classes: string}>
     */
    public const STATUS_META = [
        'done' => ['label' => 'Done', 'classes' => 'bg-emerald-100 text-emerald-700'],
        'in_progress' => ['label' => 'In progress', 'classes' => 'bg-amber-100 text-amber-700'],
        'missed' => ['label' => 'Missed', 'classes' => 'bg-rose-100 text-rose-700'],
        'not_started' => ['label' => 'Not started', 'classes' => 'bg-[#F3ECE0] text-[#2D231E]'],
    ];

    /** Label + pill classes for a progress state, one shared source. */
    public static function statusMeta(string $state): array
    {
        return static::STATUS_META[$state] ?? ['label' => $state, 'classes' => 'bg-[#F3ECE0] text-[#2D231E]'];
    }

    /**
     * Per-request quiz lookup memo (hasQuiz hits the database; assignment
     * rollups call it per audience member).
     */
    protected ?bool $quizMemo = null;

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'assigned_at' => 'datetime',
            'finished_at' => 'datetime',
            'grade_level' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AssignmentTarget::class);
    }

    public function targetUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignment_targets');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(AssignmentMark::class);
    }

    /**
     * Whether a teacher manually marked this student done (any scope — the
     * mark table is independent of assignment_targets).
     */
    public function markedCompleteFor(User $student): bool
    {
        return $this->marks()->where('user_id', $student->id)->exists();
    }

    /**
     * Teacher's manual "done" mark ORs into completion so the rollup,
     * compliance count, hub pill and new-assignment toast all reflect the
     * recorded result. Evidence-based read/quiz flags stay untouched so the
     * student checklist remains truthful.
     */
    public function completedFor(User $student): bool
    {
        return $this->completedByEvidence($student) || $this->markedCompleteFor($student);
    }

    /**
     * Bulk completion lookup for many students at once, mirroring the
     * single-student completedFor()/studentProgress() semantics. Read, quiz
     * and manual-mark state are resolved in three IN() scans (per dirty
     * table) instead of a per-student exists() call, so teacher rollups stay
     * flat as classrooms grow. Keyed by student id. Row shape:
     *
     *   - `read`/`quiz`  evidence flags (studentProgress parity)
     *   - `marked`       manual teacher mark (markedCompleteFor parity)
     *   - `completed`    evidence-only completion (studentProgress parity)
     *   - `done`         completed OR marked (completedFor parity)
     *
     * @param  Collection<int, User>|array<int, User>  $students
     * @return array<int, array{read: bool, quiz: bool, marked: bool, completed: bool, done: bool}>
     */
    public function completionMapFor(iterable $students): array
    {
        $map = [];

        if ($this->story_id === null) {
            foreach ($students as $student) {
                $map[(int) $student->id] = ['read' => false, 'quiz' => false, 'marked' => false, 'completed' => false, 'done' => false];
            }

            return $map;
        }

        $ids = collect($students)->map(fn (User $student) => (int) $student->id);

        if ($ids->isEmpty()) {
            return [];
        }

        $since = $this->gateStartsAt();

        $readIds = $since !== null
            ? StoryProgress::query()
                ->whereIn('user_id', $ids->all())
                ->where('story_id', $this->story_id)
                ->whereIn('status', [StoryProgressStatus::Read->value, StoryProgressStatus::Completed->value])
                ->where('completed_at', '>=', $since)
                ->distinct()
                ->pluck('user_id')
                ->flip()
            : [];

        $hasQuiz = $this->hasQuiz();

        $quizIds = $hasQuiz && $since !== null
            ? TestAttempt::query()
                ->whereIn('user_id', $ids->all())
                ->where('story_id', $this->story_id)
                ->where('created_at', '>=', $since)
                ->distinct()
                ->pluck('user_id')
                ->flip()
            : [];

        $markedIds = $this->marks()
            ->whereIn('user_id', $ids->all())
            ->distinct()
            ->pluck('user_id')
            ->flip();

        foreach ($ids as $id) {
            $read = isset($readIds[$id]);
            $quiz = isset($quizIds[$id]);
            $marked = isset($markedIds[$id]);
            $completed = $read && ($hasQuiz ? $quiz : true);

            $map[$id] = [
                'read' => $read,
                'quiz' => $quiz,
                'marked' => $marked,
                'completed' => $completed,
                'done' => $completed || $marked,
            ];
        }

        return $map;
    }

    /**
     * Which of the given assignments this student has already completed,
     * resolved in four bounded queries (which stories have quizzes, read
     * evidence, quiz evidence, manual marks) instead of a per-assignment
     * exists() chain. Honors completedFor() semantics (evidence read+quiz
     * OR a manual mark). Keyed by assignment id — the inverse of
     * completionMapFor(), for the hub's "N new assignments" and inbox paths.
     *
     * @param  Collection<int, Assignment>  $assignments
     * @return array<int, bool>
     */
    public static function completedStatusForStudent(User $student, Collection $assignments): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $storyIds = $assignments->pluck('story_id')->filter()->unique()->values();
        $assignmentIds = $assignments->map(fn (Assignment $assignment) => (int) $assignment->id);

        $hasQuizStoryIds = $storyIds->isNotEmpty()
            ? Story::query()
                ->whereIn('id', $storyIds)
                ->whereHas('questions')
                ->pluck('id')
                ->flip()
            : [];

        $readRows = $storyIds->isNotEmpty()
            ? StoryProgress::query()
                ->where('user_id', $student->id)
                ->whereIn('story_id', $storyIds)
                ->whereIn('status', [StoryProgressStatus::Read->value, StoryProgressStatus::Completed->value])
                ->get(['story_id', 'completed_at'])
                ->groupBy('story_id')
            : collect();

        $attemptRows = $storyIds->isNotEmpty()
            ? TestAttempt::query()
                ->where('user_id', $student->id)
                ->whereIn('story_id', $storyIds)
                ->get(['story_id', 'created_at'])
                ->groupBy('story_id')
            : collect();

        $markedIds = AssignmentMark::query()
            ->where('user_id', $student->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->pluck('assignment_id')
            ->flip();

        $map = [];

        foreach ($assignments as $assignment) {
            $isDone = false;

            if ($assignment->story_id !== null && ($since = $assignment->gateStartsAt()) !== null) {
                $read = ($readRows->get($assignment->story_id) ?? collect())
                    ->contains(fn ($row) => $row->completed_at !== null && Carbon::parse($row->completed_at)->gte($since));

                $hasQuiz = isset($hasQuizStoryIds[$assignment->story_id]);

                $quiz = $hasQuiz && ($attemptRows->get($assignment->story_id) ?? collect())
                    ->contains(fn ($row) => Carbon::parse($row->created_at)->gte($since));

                $isDone = ($read && ($hasQuiz ? $quiz : true)) || $markedIds->has($assignment->id);
            }

            $map[$assignment->id] = $isDone;
        }

        return $map;
    }

    /**
     * Completion from genuine reading/quiz evidence only (mark-agnostic).
     * Used by the reader/quiz finish transition detection so a teacher's early
     * "mark done" never suppresses the student's real completion celebration.
     */
    public function completedByEvidence(User $student): bool
    {
        return $this->studentProgress($student)['completed'];
    }

    /**
     * The number of students in this assignment's audience.
     */
    public function audienceCount(): int
    {
        return AssignmentAudience::count($this);
    }

    /**
     * Friendly label for the audience.
     */
    public function audienceLabel(): string
    {
        return AssignmentAudience::label($this);
    }

    /**
     * Whether the attached story has a quiz (any question with a correct answer).
     */
    public function hasQuiz(): bool
    {
        return $this->quizMemo ??= $this->computeHasQuiz();
    }

    private function computeHasQuiz(): bool
    {
        if ($this->story_id === null) {
            return false;
        }

        return Story::whereKey($this->story_id)
            ->whereHas('questions')
            ->exists();
    }

    /**
     * The earliest moment this assignment counts student work from.
     * Drafts that were never published fall back to their creation time, so
     * prior independent reading never accidentally satisfies a fresh task.
     *
     * @return Carbon
     */
    public function gateStartsAt()
    {
        return $this->assigned_at ?? $this->created_at;
    }

    /**
     * Per-step completion breakdown for a student, shared by the teacher
     * rollup (via completedFor) and the student's live checklist. `read` is
     * true once the story was finished after the assignment went live; `quiz`
     * is true once the student submitted any attempt (any score — engagement
     * is what counts) for the story after the gate; `completed` combines the
     * two, treating reading alone as sufficient for stories without a quiz.
     */
    public function studentProgress(User $student): array    {
        if ($this->story_id === null) {
            return ['read' => false, 'quiz' => false, 'completed' => false];
        }

        $since = $this->gateStartsAt();

        if ($since === null) {
            return ['read' => false, 'quiz' => false, 'completed' => false];
        }

        $read = StoryProgress::where('user_id', $student->id)
            ->where('story_id', $this->story_id)
            ->whereIn('status', [StoryProgressStatus::Read->value, StoryProgressStatus::Completed->value])
            ->where('completed_at', '>=', $since)
            ->exists();

        $hasQuiz = $this->hasQuiz();

        $quiz = $hasQuiz
            && TestAttempt::where('user_id', $student->id)
                ->where('story_id', $this->story_id)
                ->where('created_at', '>=', $since)
                ->exists();

        return [
            'read' => $read,
            'quiz' => $quiz,
            'completed' => $read && ($hasQuiz ? $quiz : true),
        ];
    }

    /**
     * Active assignments on a story that this student can see (newest
     * first). Shared by the reader/quiz finish detection so the one-shot
     * "Assignment complete" banner fires exactly on the true transition.
     *
     * @return Collection<int, Assignment>
     */
    public static function activeForStory(User $student, int $storyId)
    {
        return static::query()
            ->where('story_id', $storyId)
            ->where('status', 'active')
            ->with('story:id,title,cover_image')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (Assignment $assignment) => $assignment->isVisibleTo($student))
            ->values();
    }

    /**
     * How many students in the audience have completed this assignment.
     */
    public function completedCount(): int
    {
        if ($this->story_id === null) {
            return 0;
        }

        return count(array_filter(
            $this->completionMapFor(AssignmentAudience::resolve($this)->get()),
            fn (array $row) => $row['done'],
        ));
    }

    /**
     * Whether a signed-in student may open this assignment's detail page. A
     * student may open work they are part of at any status; an *active*
     * assignment can also be opened by any student who has the link (mirrors
     * the reader/share-code handoff). Cancelled work, and inactive work a
     * student isn't part of, stay locked. Shared by the student detail page
     * and the inbox follow-through so the two can never drift.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }

        $targeted = AssignmentAudience::resolve($this)
            ->whereKey($user->id)
            ->exists();

        if ($targeted) {
            return true;
        }

        return $this->status === 'active';
    }

    /**
     * The compliance rate (0-100).
     */
    public function compliancePercent(): int
    {
        $total = $this->audienceCount();
        if ($total === 0) {
            return 0;
        }

        return (int) round($this->completedCount() / $total * 100);
    }

    /**
     * Is this assignment currently past its due date?
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Has this assignment been retired into the "Finished" bucket?
     */
    public function isFinished(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Move an active/draft assignment into the "Finished" bucket: flips the
     * status to archived, stamps the moment it finished, and records the event
     * in the audit log. Archived assignments drop out of every "active" listing
     * automatically (all such queries filter on status=active) but remain
     * reviewable by the teacher and in the student's collapsed "Past" section.
     */
    public function archive(string $reason = 'manual'): void
    {
        $this->update([
            'status' => 'archived',
            'finished_at' => now(),
        ]);

        Audit::log(
            $reason === 'auto_expired' ? 'assignment.auto_archived' : 'assignment.archived',
            $this,
            ['reason' => $reason]
        );
    }
}
