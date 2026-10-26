<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAvatarRequest;
use App\Http\Requests\Settings\UpdateEmailRequest;
use App\Http\Requests\Settings\UpdateNameRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Http\Requests\Settings\UpdateTeacherProfileRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        return view('student.settings', [
            'user' => $user,
        ]);
    }

    public function updateName(UpdateNameRequest $request): RedirectResponse
    {
        $request->user()->update([
            'nickname' => $request->validated()['name'],
        ]);

        return redirect()->route('settings')->with('toast', [
            'message' => 'Display name updated.',
        ]);
    }

    public function updateEmail(UpdateEmailRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->email = $request->validated()['email'];
        $user->save();

        return redirect()->route('settings')->with('toast', [
            'message' => 'Email updated.',
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->password_changed_at = now();
        $user->save();

        $request->session()->regenerate();

        return redirect()->route('settings')->with('toast', [
            'message' => 'Password updated.',
        ]);
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $request->user()->update([
            'avatar' => $request->validated()['avatar'],
        ]);

        return redirect()->route('settings')->with('toast', [
            'message' => 'Avatar updated.',
        ]);
    }

    public function teacherIndex(): View
    {
        $user = Auth::user();

        return view('teacher.settings', [
            'user' => $user,
        ]);
    }

    public function adminIndex(): View
    {
        $user = Auth::user();

        return view('admin.settings', [
            'user' => $user,
        ]);
    }

    public function updateAdminProfile(UpdateTeacherProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('admin.settings')->with('toast', [
            'message' => 'Profile updated.',
        ]);
    }

    public function updateAdminEmail(UpdateEmailRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->email = $request->validated()['email'];
        $user->save();

        return redirect()->route('admin.settings')->with('toast', [
            'message' => 'Email updated.',
        ]);
    }

    public function updateAdminPassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->password_changed_at = now();
        $user->save();

        $request->session()->regenerate();

        return redirect()->route('admin.settings')->with('toast', [
            'message' => 'Password updated.',
        ]);
    }

    public function updateTeacherProfile(UpdateTeacherProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('teacher.settings')->with('toast', [
            'message' => 'Profile updated.',
        ]);
    }

    public function updateTeacherPassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->password = $request->validated()['password'];
        $user->password_changed_at = now();
        $user->save();

        $request->session()->regenerate();

        return redirect()->route('teacher.settings')->with('toast', [
            'message' => 'Password updated.',
        ]);
    }

    public function requestDelete(Request $request, AccountDeletionService $deletion): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->role !== Role::Student) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Use the teacher deletion option for this account.']);
        }

        if ((string) $request->input('nickname_confirm') !== (string) $user->nickname) {
            return back()->withErrors(['nickname_confirm' => 'Enter your display name to confirm.'])->withInput();
        }

        try {
            $deletion->request($user, (string) $request->input('password'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Render the confirmation directly (no redirect through guarded routes).
        // The session is kept alive so the Undo path on this screen can reach
        // settings.delete.cancel; any other authenticated request is blocked by
        // the not.deleted middleware, which signs the user out.
        return view('student.delete-confirmed', ['expires' => $user->deletionExpiresAt()]);
    }

    public function cancelDelete(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $user = $request->user();

        try {
            $deletion->cancel($user, (string) $request->input('email'), (string) $request->input('password'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $request->session()->regenerate();

        return redirect()->route('settings')->with('toast', [
            'message' => 'Deletion cancelled — welcome back!',
        ]);
    }

    public function teacherRequestDelete(Request $request, AccountDeletionService $deletion): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->role !== Role::Teacher) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Only teachers can use this option.']);
        }

        if ((string) $request->input('nickname_confirm') !== (string) $user->nickname) {
            return back()->withErrors(['nickname_confirm' => 'Enter your display name to confirm.'])->withInput();
        }

        try {
            $deletion->request($user, (string) $request->input('password'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Same pattern as the student flow: render directly, keep the session
        // alive so the Undo path reaches teacher.settings.delete.cancel.
        return view('teacher.delete-confirmed', ['expires' => $user->deletionExpiresAt()]);
    }

    public function teacherCancelDelete(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $user = $request->user();

        try {
            $deletion->cancel($user, (string) $request->input('email'), (string) $request->input('password'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $request->session()->regenerate();

        return redirect()->route('teacher.settings')->with('toast', [
            'message' => 'Deletion cancelled — welcome back!',
        ]);
    }
}
