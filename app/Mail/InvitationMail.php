<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The invitation to a new account, with the signed link where the person chooses a password. Sent synchronously (no
 * queue worker on the hosting), so it deliberately does not implement ShouldQueue.
 */
class InvitationMail extends Mailable
{
    public function __construct(public User $user, public string $url, public int $validHours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('users.mail_subject', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation',
            text: 'mail.invitation-text',
            with: ['name' => $this->user->name, 'url' => $this->url, 'validHours' => $this->validHours],
        );
    }
}
