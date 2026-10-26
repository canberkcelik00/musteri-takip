<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a user a task was given to them. Carries no money. Sent synchronously (no queue worker on the hosting), so it
 * deliberately does not implement ShouldQueue.
 */
class TaskAssignedMail extends Mailable
{
    public function __construct(public Task $task, public User $assignedBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('tasks.mail_subject', ['title' => $this->task->title]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.task-assigned',
            text: 'mail.task-assigned-text',
            with: [
                'name' => $this->task->assignee->name,
                'title' => $this->task->title,
                'customer' => $this->task->customer?->short_name,
                'dueDate' => $this->task->due_date?->format('d.m.Y'),
                'notes' => $this->task->notes,
                'assignerName' => $this->assignedBy->name,
                'url' => route('app.tasks.index'),
            ],
        );
    }
}
