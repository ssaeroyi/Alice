<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\AccountDeletionRequested;
use App\Mail\AccountRestored;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    /**
     * Soft-delete an account: scramble PII, stamp the grace window, kill every
     * session, and audit. The original email is freed for reuse immediately.
     *
     * @throws ValidationException when the supplied password is incorrect.
     */
    public function request(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        if ($user->deleted_at !== null) {
            throw ValidationException::withMessages([
                'password' => 'This account is already scheduled for deletion.',
            ]);
        }

        if ($user->role === Role::Admin) {
            throw ValidationException::withMessages([
                'password' => 'Administrator accounts cannot be deleted from here.',
            ]);
        }

        $originalEmail = $user->email;
        $displayName = $user->nickname ?: ($user->first_name ?: strtok($originalEmail, '@'));

        $this->applyDeletion($user);

        $this->sendSafely(
            new AccountDeletionRequested($user->deletionExpiresAt(), (int) config('auth.deletion_grace_days', 7), $displayName),
            $originalEmail
        );
    }

    /**
     * Admin schedules a deletion. Same soft-delete path as self-service, minus
     * the password check (the actor is an authenticated, audited admin). The
     * holder is notified at their original address before it is scrambled.
     *
     * @throws ValidationException when the account is already pending or is an admin.
     */
    public function adminRequest(User $user): void
    {
        if ($user->deleted_at !== null) {
            throw ValidationException::withMessages([
                'account' => 'This account is already scheduled for deletion.',
            ]);
        }

        if ($user->role === Role::Admin) {
            throw ValidationException::withMessages([
                'account' => 'Administrator accounts cannot be deleted from the web.',
            ]);
        }

        $originalEmail = $user->email;
        $displayName = $user->nickname ?: ($user->first_name ?: strtok($originalEmail, '@'));

        $this->applyDeletion($user, 'admin');

        $this->sendSafely(
            new AccountDeletionRequested($user->deletionExpiresAt(), (int) config('auth.deletion_grace_days', 7), $displayName),
            $originalEmail
        );
    }

    /**
     * Shared soft-delete transaction used by both self-service and admin paths.
     */
    private function applyDeletion(User $user, string $via = 'self'): void
    {
        DB::transaction(function () use ($user, $via) {
            $scrambled = 'deleted-'.$user->id.'@invalid';

            $user->forceFill([
                'email' => $scrambled,
                'first_name' => null,
                'last_name' => null,
                'nickname' => null,
                'avatar' => null,
                'preferences' => null,
                'email_verified_at' => null,
                'deleted_at' => now(),
                'deletion_requested_at' => now(),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::log('user.deletion_requested', $user, [
                'expires' => $user->deletionExpiresAt()?->toDateTimeString(),
                'initiated_by' => $via,
            ]);
        });
    }

    /**
     * Cancel a pending deletion (student/teacher self-service, or admin).
     * Restores the identity with a fresh, non-conflicting email.
     *
     * @throws ValidationException when the new email is already in use.
     */
    public function cancel(User $user, string $email, ?string $password = null, bool $verifyPassword = true): void
    {
        if ($verifyPassword && ($password === null || ! Hash::check($password, $user->password))) {
            throw ValidationException::withMessages([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        if ($user->deleted_at === null) {
            throw ValidationException::withMessages([
                'email' => 'This account is not scheduled for deletion.',
            ]);
        }

        $email = strtolower(trim($email));

        $taken = User::query()
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => 'That email address is already in use by another account.',
            ]);
        }

        DB::transaction(function () use ($user, $email) {
            $user->forceFill([
                'email' => $email,
                'deleted_at' => null,
                'deletion_requested_at' => null,
            ])->save();

            Audit::log('user.deletion_cancelled', $user, ['email' => $email]);
        });

        $this->sendSafely(new AccountRestored($user->nickname ?: strtok($email, '@')), $email);
    }

    /**
     * Send a mailable without letting a delivery failure break the caller.
     */
    private function sendSafely(Mailable $mailable, string $recipient): void
    {
        try {
            Mail::to($recipient)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Account-deletion email failed to send.', [
                'error' => $e->getMessage(),
                'recipient' => $recipient,
            ]);
        }
    }

    /**
     * Admin restores a pending-deletion account with a new email.
     */
    public function adminRestore(User $user, string $email): void
    {
        $this->cancel($user, $email, verifyPassword: false);
    }

    /**
     * Erase every account whose grace window has lapsed. Students are hard
     * deleted (FK cascade clears their data). Teachers are never deleted —
     * they were already anonymized at request time and are closed/assigned by
     * an admin; the job only prunes any that were flagged to purge without a
     * reassign (a safety valve) but otherwise only hard-deletes students and
     * strays.
     *
     * @return int number of accounts hard-deleted.
     */
    public function purgeExpired(): int
    {
        $expired = User::query()
            ->deletionExpired()
            ->get();

        $purged = 0;

        foreach ($expired as $user) {
            // Safety invariant: never hard-delete a teacher or an admin. Their
            // rows hold foreign keys (assignments, groups, stories).
            if ($user->role === Role::Teacher || $user->role === Role::Admin) {
                continue;
            }

            DB::transaction(function () use ($user) {
                Audit::log('user.deleted', $user);
                $user->delete();
            });

            $purged++;
        }

        return $purged;
    }
}
