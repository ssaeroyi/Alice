<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessage extends Mailable
{
    use Queueable;

    /**
     * A visitor's contact-submission form, forwarded to the ALICE mailbox.
     * One-shot, non-queued (the request already awaits SMTP; queueing would
     * add an async worker dependency the capstone deploy does not run).
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $topic,
        public string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New ALICE contact message: '.$this->topic);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-message'
        );
    }
}
