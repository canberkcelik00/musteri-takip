<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The test e-mail sent from the general settings page. Sent synchronously (no queue worker on
 * the hosting), so it deliberately does not implement ShouldQueue.
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('test_mail.subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.test',
            text: 'mail.test-text',
            with: ['panelUrl' => route('app.today')],
        );
    }
}
