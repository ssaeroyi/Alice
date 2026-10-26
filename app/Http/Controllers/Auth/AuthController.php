<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ConsentLog;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Support\Audit;
use App\Support\StreakRestore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            Audit::log('auth.failed_login', null, [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            // Never reveal that a deleted account existed based on the email
            // alone. Only when the submitted password matches an account still
            // inside its grace window do we offer the recovery funnel — the
            // caller proves they know the account's own password.
            if ($pending = $this->findPendingDeletionMatch((string) $request->input('password'))) {
                Audit::log('auth.deletion_restore_offered', $pending, [
                    'ip' => $request->ip(),
                    'expires' => $pending->deletionExpiresAt()?->format('F j, Y'),
                ]);

                return redirect()->route('login')
                    ->with('deletion_pending', true)
                    ->with('deletion_pending_expires', $pending->deletionExpiresAt()?->format('F j, Y'));
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($request->user()->suspended_at) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Your account is suspended. Please contact support.',
            ]);
        }

        if ($request->user()->deleted_at) {
            // Credentials matched a soft-deleted row (a pending account, or a
            // teacher anonymized for closure). Route them into the same
            // recovery funnel instead of a dead-end error.
            $deleted = $request->user();
            Auth::logout();

            Audit::log('auth.deletion_restore_offered', $deleted, [
                'ip' => $request->ip(),
                'expires' => $deleted->deletionExpiresAt()?->format('F j, Y'),
            ]);

            return redirect()->route('login')
                ->with('deletion_pending', true)
                ->with('deletion_pending_expires', $deleted->deletionExpiresAt()?->format('F j, Y'));
        }

        $request->session()->regenerate();

        $request->user()->update(['last_login_at' => now()]);

        $redirect = match ($request->user()->role) {
            Role::Admin => route('admin.dashboard'),
            Role::Teacher => route('teacher.dashboard'),
            default => $request->user()->is_setup_complete
                ? route('hub')
                : route('placement.step1'),
        };

        // A pending Streak Restore prompt takes the stage on the next page
        // load — keep the welcome banner out of its way.
        if (StreakRestore::state($request->user()) !== null) {
            return redirect()->intended($redirect);
        }

        return redirect()->intended($redirect)->with('welcome', [
            'eyebrow' => 'Welcome back',
            'title' => $this->welcomeTitle($request->user()),
            'sub' => $request->user()->current_streak >= 2
                ? "You're on a {$request->user()->current_streak}-day streak — keep it up!"
                : '',
        ]);
    }

    /**
     * A friendly display name for the returning greeting: nickname when set,
     * falling back to the student's first name, then a neutral placeholder.
     */
    private function welcomeTitle(User $user): string
    {
        return 'Welcome back, '.($user->nickname ?: ($user->first_name ?: 'reader')).'!';
    }

    /**
     * Scan the (small, age-bounded) set of accounts still inside their grace
     * window for one whose password matches — the recovery funnel's proof of
     * ownership. The original email is never retained, so we match on the
     * password the caller supplies; only its owner knows it.
     */
    private function findPendingDeletionMatch(string $password): ?User
    {
        return User::query()
            ->pendingDeletion()
            ->get()
            ->first(fn (User $user) => Hash::check($password, $user->password));
    }

    /**
     * Guest-only recovery for accounts scheduled for deletion: prove the
     * password, supply a fresh email, and the account comes fully back.
     */
    public function restoreScheduled(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $pending = $this->findPendingDeletionMatch($password);

        if ($pending === null) {
            throw ValidationException::withMessages([
                'email' => 'We could not verify that password against an account scheduled for deletion.',
            ]);
        }

        try {
            $deletion->cancel($pending, $email, $password);
        } catch (ValidationException $e) {
            return redirect()->route('login')
                ->with('deletion_pending', true)
                ->with('deletion_pending_expires', $pending->deletionExpiresAt()?->format('F j, Y'))
                ->withErrors($e->errors())
                ->withInput(['email' => $email]);
        }

        Auth::login($pending);
        $request->session()->regenerate();

        $pending->forceFill(['last_login_at' => now()])->save();

        $redirect = match ($pending->role) {
            Role::Admin => route('admin.dashboard'),
            Role::Teacher => route('teacher.dashboard'),
            default => $pending->is_setup_complete
                ? route('hub')
                : route('placement.step1'),
        };

        return redirect()->to($redirect)->with('toast', [
            'message' => 'Account restored — welcome back!',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('toast', [
            'message' => 'You have been signed out.',
        ]);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated, $request): User {
            $user = User::create([
                'email' => $validated['email'],
                'nickname' => $validated['nickname'],
                'password' => $validated['password'],
                'avatar' => Arr::random(array_column(config('avatars'), 'file')),
                'role' => Role::Student,
            ]);

            ConsentLog::create([
                'user_id' => $user->id,
                'type' => 'terms_accepted',
                'document_version' => '1.0',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'accepted_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('placement.step1')->with('toast', [
            'message' => 'Account ready — set up your profile to begin.',
        ]);
    }
}
