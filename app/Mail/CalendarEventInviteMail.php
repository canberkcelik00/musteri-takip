<?php

namespace App\Mail;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a user they were added to a calendar event. Carries no money. Sent synchronously (no queue worker on the
 * hosting), so it deliberately does not implement ShouldQueue.
 */
class CalendarEventInviteMail extends Mailable
{
    public function __construct(public CalendarEvent $event, public User $attendee, public User $invitedBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('calendar.mail_subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.calendar-event-invite',
            text: 'mail.calendar-event-invite-text',
            with: [
                'name' => $this->attendee->name,
                'title' => $this->event->title,
                'when' => $this->describeTime(),
                'repeat' => $this->event->repeats() ? $this->event->repeat->getLabel() : null,
                'customer' => $this->event->customer?->short_name,
                'location' => $this->event->location,
                'notes' => $this->event->notes,
                'inviterName' => $this->invitedBy->name,
                'url' => url('/calendar'),
            ],
        );
    }

    private function describeTime(): string
    {
        $date = $this->event->starts_on->format('d.m.Y');

        if ($this->event->all_day || $this->event->start_time === null) {
            return $date.' · '.__('calendar.all_day');
        }

        $time = substr($this->event->start_time, 0, 5);

        if ($this->event->end_time !== null) {
            $time .= '–'.substr($this->event->end_time, 0, 5);
        }

        return $date.' · '.$time;
    }
}
