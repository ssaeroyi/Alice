<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NudgeSetupPending extends Mailable
{
    public function __construct(
        public User $student,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are not done yet',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.nudge-setup');
    }
}
