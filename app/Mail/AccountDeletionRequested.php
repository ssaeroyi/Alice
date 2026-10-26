<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountDeletionRequested extends Mailable
{
    public function __construct(
        public CarbonInterface $expiresAt,
        public int $graceDays,
        public string $displayName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your ALICE account is scheduled for deletion');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-deletion-requested'
        );
    }
}
