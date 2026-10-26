<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTeacherRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Mail\TeacherInvitation;
use App\Models\AuditLog;
use App\Models\ConsentLog;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Support\Audit;
use App\Support\ViewAs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * List all accounts (students, teachers, admins) with search and filters.
     * Account-level data only — no learning analytics here.
     */
    public function index(Request $request): View
    {
        $sort = $request->string('sort')->toString();

        if (! in_array($sort, ['newest', 'oldest', 'name_asc', 'name_desc'], true)) {
            $sort = 'newest';
        }

        $users = static::filteredQuery($request)
            ->when($sort === 'newest', fn ($query) => $query->latest('created_at'))
            ->when($sort === 'oldest', fn ($query) => $query->oldest('created_at'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('nickname')->orderByDesc('created_at'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('nickname')->orderByDesc('created_at'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.accounts.index', [
            'users' => $users,
            'sort' => $sort,
        ]);
    }

    /**
     * Download the current filtered account list as a CSV that opens in Excel.
     */
    public function exportCsv(Request $request)
    {
        $users = static::filteredQuery($request)
            ->latest('created_at')
            ->get();

        Audit::log('user.exported_csv', null, ['count' => $users->count()]);

        $filename = 'accounts-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nickname', 'Email', 'First name', 'Last name', 'Role', 'Grade', 'Reading level', 'Status', 'Joined', 'Last login']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->nickname,
                    $user->email,
                    $user->first_name,
                    $user->last_name,
                    $user->role->label(),
                    $user->grade_level ? 'Grade '.$user->effectiveGrade() : '',
                    $user->reading_level ? 'Level '.$user->reading_level : '',
                    $user->deleted_at
                        ? 'Deletion pending (expires '.$user->deletionExpiresAt()?->format('Y-m-d').')'
                        : ($user->suspended_at ? 'Suspended' : ($user->is_setup_complete ? 'Active' : 'Setup pending')),
                    $user->created_at->format('Y-m-d H:i'),
                    $user->last_login_at?->format('Y-m-d H:i') ?? 'Never',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<User>
     */
    private static function filteredQuery(Request $request)
    {
        return User::query()
            ->whereNot('email', 'like', '%@'.ViewAs::SCRATCH_DOMAIN)
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('nickname', 'like', '%'.$request->q.'%')
                    ->orWhere('email', 'like', '%'.$request->q.'%')
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$request->q.'%']);
            }))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->when($request->filled('grade'), fn ($query) => $query->where('grade_level', $request->grade))
            ->when($request->filled('level'), fn ($query) => $query->where('reading_level', $request->level))
            ->when(
                $request->filled('status') && $request->status === 'suspended',
                fn ($query) => $query->whereNotNull('suspended_at')->whereNull('deleted_at')
            )
            ->when(
                $request->filled('status') && $request->status === 'active',
                fn ($query) => $query->whereNull('suspended_at')->whereNull('deleted_at')
            )
            ->when(
                $request->filled('status') && $request->status === 'deletion-pending',
                fn ($query) => $query->pendingDeletion()
            )
            ->when(
                $request->filled('setup') && $request->setup === 'done',
                fn ($query) => $query->where('is_setup_complete', true)
            )
            ->when(
                $request->filled('setup') && $request->setup === 'pending',
                fn ($query) => $query->where('is_setup_complete', false)
            );
    }

    public function create(): View
    {
        return view('admin.accounts.create');
    }

    public function store(CreateTeacherRequest $request): RedirectResponse
    {
        $user = User::create([
            'nickname' => $request->nickname,
            'email' => $request->email,
            'password' => $request->password,
            'role' => Role::Teacher,
            'email_verified_at' => now(),
            'is_setup_complete' => true,
        ]);

        Audit::log('user.created', $user, ['role' => $user->role->value]);

        try {
            Mail::to($user->email)->send(new TeacherInvitation($user, $request->password));
        } catch (\Throwable $e) {
            Log::error('Teacher invitation failed to send.', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }

        return redirect()->route('admin.accounts.show', $user)->with('toast', [
            'message' => 'Teacher account created. Sign-in details sent to '.$user->email.'.',
        ]);
    }

    public function show(User $user): View
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get();

        $consents = ConsentLog::query()
            ->where('user_id', $user->id)
            ->latest('accepted_at')
            ->get();

        $audit = AuditLog::query()
            ->where('target_type', $user->getMorphClass())
            ->where('target_id', $user->id)
            ->with('actor')
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('admin.accounts.show', [
            'account' => $user,
            'sessions' => $sessions,
            'consents' => $consents,
            'audit' => $audit,
        ]);
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot suspend your own account.']);
        }

        if ($user->suspended_at !== null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This account is already suspended.']);
        }

        $user->forceFill(['suspended_at' => now()])->save();

        $sessions = DB::table('sessions')->where('user_id', $user->id)->delete();

        Audit::log('user.suspended', $user, ['reason' => $request->reason, 'sessions' => $sessions]);

        $name = $user->nickname ?? strtok($user->email, '@');

        return back()->with('toast', [
            'type' => 'success',
            'message' => $sessions > 0
                ? $name.' has been suspended — '.$sessions.' active session'.($sessions === 1 ? '' : 's').' signed out immediately.'
                : $name.' has been suspended. Sign-in is blocked.',
        ]);
    }

    public function reactivate(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot reactivate your own account.']);
        }

        if ($user->suspended_at === null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This account is not suspended.']);
        }

        $user->forceFill(['suspended_at' => null])->save();

        Audit::log('user.reactivated', $user);

        return back()->with('toast', [
            'message' => ($user->nickname ?? strtok($user->email, '@')).' can sign in again.',
        ]);
    }

    /**
     * Restore (undelete) a pending-deletion account and hand it a fresh email.
     */
    public function restore(Request $request, User $user, AccountDeletionService $deletion): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot restore your own account.']);
        }

        if ($user->deleted_at === null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This account is not scheduled for deletion.']);
        }

        $email = strtolower(trim((string) $request->input('email')));

        try {
            $deletion->adminRestore($user, $email);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        Audit::log('user.restored', $user, ['email' => $email]);

        return back()->with('toast', [
            'message' => ($user->nickname ?? strtok($email, '@')).' has been restored and can sign in again.',
        ]);
    }

    /**
     * Admin schedules an account for deletion (same reversible grace window
     * as self-service). The holder is notified before the address is scrambled.
     */
    public function destroy(Request $request, User $user, AccountDeletionService $deletion): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot delete your own account.']);
        }

        if ($user->role === Role::Admin) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Administrator accounts cannot be deleted from the web.']);
        }

        if ($user->deleted_at !== null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This account is already scheduled for deletion.']);
        }

        $name = $user->nickname ?? strtok($user->email, '@');

        $deletion->adminRequest($user);

        $expires = $user->fresh()->deletionExpiresAt()?->format('M j, Y') ?? 'soon';

        return back()->with('toast', [
            'type' => 'success',
            'message' => $name.' is scheduled for deletion. Restore this account before '.$expires.' to undo it.',
        ]);
    }

    public function resetLink(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot reset your own password here.']);
        }

        Audit::log('user.reset_link', $user);

        try {
            Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            Log::error('Account reset link failed to send.', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }

        return back()->with('toast', [
            'message' => 'A password reset link has been sent to '.$user->email.'.',
        ]);
    }

    public function changeRole(Request $request, User $user): RedirectResponse
    {
        $role = Role::tryFrom((string) $request->input('role'));

        if ($role === null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Please choose a valid role.']);
        }

        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot change your own role.']);
        }

        if ($user->role === Role::Admin || $role === Role::Admin) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Admin roles are managed from the command line only (php artisan admin:manage set-role).',
            ]);
        }

        if ($role === $user->role) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This account already has that role.']);
        }

        $previous = $user->role;
        $user->forceFill(['role' => $role])->save();

        Audit::log('user.role_changed', $user, ['from' => $previous->value, 'to' => $role->value]);

        return back()->with('toast', [
            'message' => 'Role updated to '.$role->label().'.',
        ]);
    }

    public function signOut(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'You cannot sign out your own account here.']);
        }

        $count = DB::table('sessions')->where('user_id', $user->id)->delete();

        Audit::log('user.signed_out', $user, ['sessions' => $count]);

        return back()->with('toast', [
            'message' => $count > 0
                ? 'Signed out '.$count.' active '.($count === 1 ? 'session' : 'sessions').'.'
                : 'No active sessions were found.',
        ]);
    }

    /**
     * One-click admin "view as" — switch to the disposable scratch account for
     * the given role (Student or Teacher). Preview activity is written only to
     * the throwaway scratch account, never a real learner/teacher, and is reset
     * on return-to-admin.
     */
    public function quickViewAs(string $role): RedirectResponse
    {
        $role = Role::tryFrom($role);

        if ($role === null) {
            abort(404);
        }

        $scratch = $role === Role::Teacher
            ? ViewAs::scratchTeacher()
            : ViewAs::scratchStudent();

        if ($scratch === null) {
            Audit::log('preview.switch_failed', null, ['role' => $role->value]);

            return redirect()->route('admin.dashboard')->with('toast', [
                'type' => 'error',
                'message' => 'Could not prepare a scratch '.strtolower($role->label()).' preview account.',
            ]);
        }

        if (! ViewAs::start($scratch)) {
            Audit::log('preview.switch_failed', $scratch, ['role' => $role->value]);

            return redirect()->route('admin.dashboard')->with('toast', [
                'type' => 'error',
                'message' => 'A preview is already active. Return to admin first.',
            ]);
        }

        Audit::log('preview.started', $scratch, ['role' => $role->value]);

        $home = $role === Role::Teacher ? route('teacher.dashboard') : route('hub');

        return redirect($home)->with('toast', [
            'message' => 'Viewing as '.$scratch->nickname.'. Use "Return to Admin" when done.',
        ]);
    }
}
