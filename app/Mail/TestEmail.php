<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ALICE — Test email');
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.test-email'
        );
    }
}
