<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountRestored extends Mailable
{
    public function __construct(
        public string $displayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ALICE account has been restored');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-restored'
        );
    }
}
