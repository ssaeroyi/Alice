<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\GameRound;
use App\Models\KeyTransaction;
use App\Models\ReadingProgress;
use App\Models\SavedWord;
use App\Models\StoryProgress;
use App\Models\TestAttempt;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserDailyActivity;
use App\Models\UserDailyTask;
use App\Models\WordGymDeck;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Reversible admin "view as" impersonation — scratch-preview only.
 *
 * Admin previews NEVER step into a real learner/teacher account (that would
 * write invasive real activity — reading time, quizzes, badges — into a real
 * user's data). Instead the admin switches to one of two dedicated, disposable
 * "scratch" preview identities: a Student and a Teacher. Preview activity goes
 * only to that throwaway account, which is reset on return (see
 * `resetTarget()`).
 *
 * Because the student/teacher zones are gated by middlewares that treat
 * admin/teacher sessions as staff (`EnsureNotStaff` -> 403) and that can
 * destroy a session on a suspended user (`EnsureNotSuspended` ->
 * Auth::logout + invalidate), impersonation is NOT a flag on a live staff
 * session — it is a real session switch. On start we stash the true (admin)
 * actor id in the session and `Auth::login($target)` so every downstream
 * gate/redirect behaves exactly as if the target had logged in. `stop()`
 * restores the real actor.
 *
 * Safety invariants (all enforced here so a caller cannot bypass them):
 *  - the target must be a Student or a Teacher (no admin->admin climb);
 *  - a suspended target is refused (never lets `EnsureNotSuspended` wipe the
 *    real session);
 *  - switching while already viewing-as is a no-op (no nesting);
 *  - a scratch resolution that does not land on a matching scratch account of
 *    the expected role & non-suspended status is refused (never preview a real
 *    or mismatched user by accident).
 *
 * The true actor is recorded so audit writes stay attributable to the admin.
 * Telemetry (`request_logs`) intentionally remains attributed to the previewed
 * user — no schema change (documented tradeoff; harmless against scratch
 * accounts).
 */
class ViewAs
{
    public const KEY = 'alice.view_as_actor';

    /** Email-domain prefix marker identifying disposable scratch accounts. */
    public const SCRATCH_DOMAIN = 'scratch.alice.local';

    public const SCRATCH_STUDENT_EMAIL = 'preview.student@'.self::SCRATCH_DOMAIN;

    public const SCRATCH_TEACHER_EMAIL = 'preview.teacher@'.self::SCRATCH_DOMAIN;

    /** The user currently being previewed (the session user) or null. */
    public static function target(): ?User
    {
        return Auth::user() instanceof User ? Auth::user() : null;
    }

    /** The real administrator who initiated the preview, or null. */
    public static function actor(): ?User
    {
        $id = session()->get(self::KEY);

        if (! $id) {
            return null;
        }

        $actor = User::find($id);

        return $actor && $actor->role === Role::Admin ? $actor : null;
    }

    public static function isActive(): bool
    {
        return self::actor() !== null;
    }

    /** True when the current (previewed) user is one of the scratch accounts. */
    public static function isScratchTarget(?User $user = null): bool
    {
        $user ??= self::target();

        return $user !== null
            && str_ends_with(strtolower($user->email), '@'.self::SCRATCH_DOMAIN);
    }

    /**
     * Resolve (or defensively create) the scratch Student preview account.
     * Returns null only if an account of that email exists but is NOT a
     * usable scratch student (mismatch guard — never return a real user).
     */
    public static function scratchStudent(): ?User
    {
        return self::scratch(Role::Student, self::SCRATCH_STUDENT_EMAIL, 'Preview Student');
    }

    /**
     * Resolve (or defensively create) the scratch Teacher preview account.
     * Returns null only if an account of that email exists but is NOT a
     * usable scratch teacher (mismatch guard — never return a real user).
     */
    public static function scratchTeacher(): ?User
    {
        return self::scratch(Role::Teacher, self::SCRATCH_TEACHER_EMAIL, 'Preview Teacher');
    }

    /**
     * Find-or-create a single scratch preview account.
     *
     * Edge case: if the fixed email already belongs to a non-scratch account
     * (e.g. manually imported with that address) we must NOT treat it as
     * disposable — resolve null so the caller refuses instead of previewing a
     * real user by accident.
     */
    private static function scratch(Role $role, string $email, string $nickname): ?User
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            $usable = str_ends_with(strtolower($user->email), '@'.self::SCRATCH_DOMAIN)
                && $user->role === $role
                && $user->suspended_at === null;

            return $usable ? $user : null;
        }

        return User::create([
            'nickname' => $nickname,
            'email' => $email,
            'password' => Hash::make((string) str()->random(48)),
            'role' => $role,
            'email_verified_at' => now(),
            'is_setup_complete' => true,
            'suspended_at' => null,
        ]);
    }

    /**
     * Begin viewing-as the given user.
     *
     * @return bool true when the switch happened, false when refused.
     */
    public static function start(User $target): bool
    {
        if (self::isActive()) {
            return false;
        }

        if ($target->role !== Role::Student && $target->role !== Role::Teacher) {
            return false;
        }

        if ($target->suspended_at) {
            return false;
        }

        session()->put(self::KEY, Auth::id());
        session()->save();

        Auth::login($target);

        return true;
    }

    /** End the preview and restore the real actor. Returns true if restored. */
    public static function stop(): bool
    {
        $actor = self::actor();

        if (! $actor) {
            session()->forget(self::KEY);
            session()->save();

            return false;
        }

        session()->forget(self::KEY);
        session()->save();
        Auth::login($actor);

        return true;
    }

    /**
     * Wipe all learner-generated preview data for the given scratch account so
     * the next preview starts clean. Only ever touches a scratch account
     * (verified by domain) — a real user is never modified. Returns the number
     * of rows removed.
     */
    public static function resetTarget(User $user): int
    {
        if (! self::isScratchTarget($user)) {
            return 0;
        }

        $removed = 0;

        foreach ([
            UserDailyActivity::class,
            StoryProgress::class,
            ReadingProgress::class,
            TestAttempt::class,
            GameRound::class,
            UserDailyTask::class,
            SavedWord::class,
            UserBadge::class,
            KeyTransaction::class,
            WordGymDeck::class,
        ] as $model) {
            $removed += $model::query()->where('user_id', $user->id)->delete();
        }

        $user->forceFill([
            'total_keys' => 0,
            'points' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'streak_freezes' => 0,
            'last_stamped_date' => null,
        ])->save();

        return $removed;
    }
}
