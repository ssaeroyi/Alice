<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            Password::sendResetLink(['email' => $request->validated()['email']]);
        } catch (\Throwable $e) {
            Log::error('Password reset link failed to send.', [
                'error' => $e->getMessage(),
            ]);
        }

        // Always the same generic response so an unregistered email is never
        // revealed. The SMTP send is synchronous, so the submit-guard spinner
        // covers the delay while this runs.
        return redirect()->route('password.request')->with('toast', [
            'message' => 'If your email is registered, we have sent a reset link. It may take a minute to arrive.',
        ]);
    }

    public function showReset(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $request->input('token'),
            'email' => $request->input('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('toast', [
                'message' => 'Your password has been reset. Sign in with your new password.',
            ]);
        }

        return back()->withErrors([
            'email' => trans($status),
        ]);
    }
}
