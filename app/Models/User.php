<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ComprehensionLevel;
use App\Enums\GradeLevel;
use App\Enums\Role;
use App\Notifications\ResetPassword;
use App\Support\SchoolYear;
use App\Support\ViewAs;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

    #[Fillable(['first_name', 'last_name', 'nickname', 'avatar', 'email', 'password', 'role', 'grade_level', 'grade_anchor_year', 'grade_hold_school_year', 'comprehension_level', 'comprehension_bar', 'reading_level', 'section', 'last_viewed_assignment_at', 'is_setup_complete', 'last_login_at', 'last_seen_at', 'last_nudged_at', 'last_nudge_kind', 'suspended_at', 'deleted_at', 'deletion_requested_at', 'total_keys', 'points', 'rank_title', 'current_streak', 'longest_streak', 'streak_freezes', 'streak_freezes_used', 'streak_freezes_bought', 'last_stamped_date', 'preferences', 'daily_reading_goal', 'daily_study_goal'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_nudged_at' => 'datetime',
            'suspended_at' => 'datetime',
            'deleted_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'last_viewed_assignment_at' => 'datetime',
            'role' => Role::class,
            'grade_level' => GradeLevel::class,
            'grade_anchor_year' => 'integer',
            'grade_hold_school_year' => 'integer',
            'comprehension_level' => ComprehensionLevel::class,
            'comprehension_bar' => 'integer',
            'reading_level' => 'integer',
            'is_setup_complete' => 'boolean',
            'total_keys' => 'integer',
            'points' => 'integer',
            'rank_title' => 'string',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'streak_freezes' => 'integer',
            'streak_freezes_used' => 'integer',
            'streak_freezes_bought' => 'integer',
            'last_stamped_date' => 'date',
            'preferences' => 'array',
            'daily_reading_goal' => 'integer',
            'daily_study_goal' => 'integer',
        ];
    }

    /**
     * The grade to display right now.
     *
     * The grade reported during placement is anchored to the school year it
     * was set in; each new school year (June) it advances by one, capped at
     * grade 10 (Junior High). Reading-level derivation keeps using the
     * stored grade_level — this method is for display only.
     */
    public function effectiveGrade(): int
    {
        $reported = (int) ($this->grade_level?->value ?? 0);

        if ($reported === 0) {
            return 0;
        }

        if ($this->grade_hold_school_year === SchoolYear::startYear()) {
            return min(10, $reported);
        }

        $anchor = $this->grade_anchor_year ?? SchoolYear::startYear();
        $years = max(0, SchoolYear::startYear() - $anchor);

        return min(10, $reported + $years);
    }

    public function placementAttempt(): HasOne
    {
        return $this->hasOne(PlacementAttempt::class);
    }

    public function savedWords(): HasMany
    {
        return $this->hasMany(SavedWord::class);
    }

    public function readingProgress(): HasOne
    {
        return $this->hasOne(ReadingProgress::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    public function storyProgress(): HasMany
    {
        return $this->hasMany(StoryProgress::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderByDesc('created_at');
    }

    public function joinedGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
            ->withTimestamps()
            ->withPivot('joined_at');
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->unread()->orderByDesc('created_at');
    }

    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    /**
     * Active assignments targeting this student (by class/group, grade, or
     * explicit target).
     */
    public function activeAssignments()
    {
        $groupIds = $this->joinedGroups()->pluck('groups.id')->all();

        return Assignment::query()
            ->where('status', 'active')
            ->where(function ($q) use ($groupIds) {
                $q->where('scope', 'all')
                    ->orWhere(function ($sq) use ($groupIds) {
                        $sq->where('scope', 'group')->whereIn('group_id', $groupIds);
                    })
                    ->orWhere(function ($sq) {
                        $sq->where('scope', 'students')
                            ->whereHas('targets', fn ($tq) => $tq->where('user_id', $this->id));
                    });
            })
            ->with('story')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Finished (archived) assignments targeting this student (by class/group,
     * grade, or explicit target). Kept reachable so past work stays reviewable
     * in the hub's collapsed "Past assignments" section.
     */
    public function finishedAssignments()
    {
        $groupIds = $this->joinedGroups()->pluck('groups.id')->all();

        return Assignment::query()
            ->where('status', 'archived')
            ->where(function ($q) use ($groupIds) {
                $q->where('scope', 'all')
                    ->orWhere(function ($sq) use ($groupIds) {
                        $sq->where('scope', 'group')->whereIn('group_id', $groupIds);
                    })
                    ->orWhere(function ($sq) {
                        $sq->where('scope', 'students')
                            ->whereHas('targets', fn ($tq) => $tq->where('user_id', $this->id));
                    });
            })
            ->with('story')
            ->orderByDesc('finished_at')
            ->get();
    }

    public function newAssignmentsCount(): int
    {
        return $this->newAssignments()->count();
    }

    /**
     * Active assignments created after the student last viewed them, resolved
     * to this student's audience. Assignments the student has already finished
     * are excluded so the hub's "N new assignments" toast only ever warns about
     * genuinely new, unfinished work ("even though it was already done" fix).
     */
    public function newAssignments(): Collection
    {
        $since = $this->last_viewed_assignment_at;
        $groupIds = $this->joinedGroups()->pluck('groups.id')->all();

        $assignments = Assignment::query()
            ->where('status', 'active')
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->where(function ($q) use ($groupIds) {
                $q->where('scope', 'all')
                    ->orWhere(fn ($sq) => $sq->where('scope', 'group')->whereIn('group_id', $groupIds))
                    ->orWhere(fn ($sq) => $sq->where('scope', 'students')->whereHas('targets', fn ($tq) => $tq->where('user_id', $this->id)));
            })
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        // Which of these are already completed — one bounded batch instead of
        // a per-assignment read/quiz/mark exists() chain.
        $done = Assignment::completedStatusForStudent($this, $assignments);

        return $assignments
            ->reject(fn (Assignment $assignment) => $done[$assignment->id] ?? false)
            ->values();
    }

    /**
     * Mark all currently active assignments as seen.
     */
    public function markAssignmentsViewed(): void
    {
        $this->update(['last_viewed_assignment_at' => now()]);
    }

    /**
     * Whether this account has requested (soft) deletion and is inside its
     * reversible grace window. A deleted-at account is entry-blocked at the
     * middleware and can only be restored by the user (seconds of the request)
     * or an admin.
     */
    public function isPendingDeletion(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * The grace window (requested -> purge). Configurable via
     * config('auth.deletion_grace_days'); defaults to 7.
     */
    public function deletionExpiresAt(): ?CarbonInterface
    {
        if ($this->deleted_at === null) {
            return null;
        }

        return $this->deleted_at->addDays((int) config('auth.deletion_grace_days', 7));
    }

    /**
     * Scope: accounts sitting in the reversible grace window (deleted but not
     * yet purged) — the admin "Deletion pending" queue.
     */
    public function scopePendingDeletion($query)
    {
        return $query->whereNotNull('deleted_at');
    }

    /**
     * Scope: accounts whose grace window has lapsed and are ready for the
     * nightly finalization pass (students are hard-deleted, teachers are
     * already anonymized and awaiting admin closure).
     */
    public function scopeDeletionExpired($query)
    {
        $cutoff = now()->subDays((int) config('auth.deletion_grace_days', 7));

        return $query->whereNotNull('deleted_at')->where('deleted_at', '<=', $cutoff);
    }

    /**
     * Scope: real, production accounts only — excludes scratch/preview
     * (@scratch.alice.local) and demo (@example.com) accounts from
     * user-facing lists. Apply to any query that returns accounts for
     * display (leaderboards, rosters, activity feeds, admin lists).
     */
    public function scopeRealAccounts($query)
    {
        return $query
            ->whereNot('email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
            ->whereNot('email', 'like', '%@example.com')
            ->whereNot('email', 'like', '%@alice.test');
    }

    /**
     * The freshest of last-seen and last-login, so presence stays honest
     * for accounts created before last_seen_at existed.
     */
    public function lastActiveAt(): ?\Carbon\CarbonInterface
    {
        return collect([$this->last_seen_at, $this->last_login_at])
            ->filter()
            ->sortDesc()
            ->first();
    }

    /**
     * Which nudge template fits this student right now: they either never
     * finished the placement check (setup) or drifted away mid-reading
     * (inactive). Null when neither applies (recently active).
     */
    public function nudgeKind(): ?string
    {
        if (! $this->is_setup_complete) {
            return 'setup';
        }

        $lastActive = $this->lastActiveAt();

        return ($lastActive === null || $lastActive->lt(now()->subDays(3))) ? 'inactive' : null;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
