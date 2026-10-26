<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Forward a Contact Us form submission to the ALICE mailbox. The form is
     * public (guest + student), so it is throttled and full-validated to curb
     * spam. Recipient comes from config('mail.contact_to') (the MAIL_CONTACT_TO
     * env var), falling back to the global MAIL_FROM_ADDRESS.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'topic' => ['required', 'string', 'in:general,bug,story,other'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $recipient = config('mail.contact_to') ?? config('mail.from.address');

        try {
            Mail::to($recipient)->send(new ContactMessage(
                $validated['name'],
                $validated['email'],
                $validated['topic'],
                $validated['message'],
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['form' => 'Sorry, we could not send your message right now. Please try again later.']);
        }

        return back()->with('contact_sent', true);
    }
}
