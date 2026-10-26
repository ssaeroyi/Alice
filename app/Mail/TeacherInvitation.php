<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TeacherInvitation extends Mailable
{
    public function __construct(
        public User $user,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been added to ALICE as a teacher');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.teacher-invitation'
        );
    }
}
