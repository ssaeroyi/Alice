<?php

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

test('guests see the contact page with the working form', function () {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('CONTACT US')
        ->assertSee(route('contact.submit'));
});

test('a valid contact submission forwards the message to the ALICE mailbox', function () {
    config(['mail.contact_to' => 'alicemakesyouread@gmail.com']);

    $this->post(route('contact.submit'), [
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'topic' => 'bug',
        'message' => 'I found a small problem in the reader.',
    ])
        ->assertRedirect()
        ->assertSessionHas('contact_sent');

    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->hasTo('alicemakesyouread@gmail.com')
            && $mail->name === 'Maria'
            && $mail->email === 'maria@example.com'
            && $mail->topic === 'bug'
            && $mail->message === 'I found a small problem in the reader.';
    });
});

test('contact submission falls back to the from address when contact_to is not set', function () {
    config(['mail.contact_to' => null]);
    config(['mail.from.address' => 'alicemakesyouread@gmail.com']);

    $this->post(route('contact.submit'), [
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'topic' => 'general',
        'message' => 'Hello, I have a question about ALICE.',
    ])
        ->assertRedirect()
        ->assertSessionHas('contact_sent');

    Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
        return $mail->hasTo('alicemakesyouread@gmail.com');
    });
});

test('an invalid contact submission is rejected with validation errors', function () {
    $this->post(route('contact.submit'), [
        'name' => '',
        'email' => 'not-an-email',
        'topic' => 'nope',
        'message' => 'short',
    ])
        ->assertSessionHasErrors(['name', 'email', 'topic', 'message']);

    Mail::assertNothingSent();
});

test('a mail failure shows a friendly error and keeps the input', function () {
    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new RuntimeException('smtp down'));

    $this->post(route('contact.submit'), [
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'topic' => 'other',
        'message' => 'A reasonably long message that passes validation.',
    ])
        ->assertSessionHasErrors('form')
        ->assertSessionHas('errors');

    $this->get(route('contact'))->assertOk();
});
