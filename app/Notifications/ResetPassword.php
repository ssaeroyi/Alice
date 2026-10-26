<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseResetPassword
{
    /**
     * Build a request-aware reset URL. The stock implementation uses
     * config('app.url'), which points at localhost:8000 and breaks the
     * link. Using route() with an absolute URL keeps the Laragon base
     * path (e.g. /Alice-Ver2/public) intact.
     */
    protected function resetUrl($notifiable)
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Reset your ALICE password')
            ->view('mail.reset-password', [
                'url' => $url,
                'expire' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
