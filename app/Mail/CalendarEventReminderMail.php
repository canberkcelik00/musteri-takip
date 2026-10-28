<?php

namespace App\Mail;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Reminds an attendee that one day (`$occursOn`) of a calendar event is about to start. Carries no money. Sent
 * synchronously (no queue worker on the hosting), so it deliberately does not implement ShouldQueue.
 */
class CalendarEventReminderMail extends Mailable
{
    public function __construct(public CalendarEvent $event, public User $attendee, public Carbon $occursOn) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('calendar.reminder_mail.subject', ['title' => $this->event->title, 'when' => $this->describeTime()]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.calendar-event-reminder',
            text: 'mail.calendar-event-reminder-text',
            with: [
                'name' => $this->attendee->name,
                'title' => $this->event->title,
                'when' => $this->describeTime(),
                'customer' => $this->event->customer?->short_name,
                'location' => $this->event->location,
                'notes' => $this->event->notes,
                'url' => url('/calendar'),
            ],
        );
    }

    private function describeTime(): string
    {
        $date = $this->occursOn->format('d.m.Y');

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
