<?php

namespace App\Actions\Tasks;

use App\Exceptions\InvalidTask;
use App\Exceptions\TaskMailNotSent;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;

class CreateTask
{
    public function __construct(private SendTaskAssignedMail $sendTaskAssignedMail) {}

    /**
     * Create a task. Without an assignee it goes to `$actor`; assigning it to someone else e-mails them, but a failed
     * e-mail leaves the task saved.
     *
     * @param  array{title: string, notes?: ?string, customer_id?: ?int, assigned_to?: ?int, due_date?: mixed}  $attributes
     *
     * @throws InvalidTask the assignee is deactivated or the customer is archived
     * @throws TaskMailNotSent
     */
    public function handle(User $actor, array $attributes): Task
    {
        $assignee = isset($attributes['assigned_to']) ? User::query()->findOrFail($attributes['assigned_to']) : $actor;

        if (! $assignee->isActive()) {
            throw InvalidTask::assigneeInactive();
        }

        $customerId = $attributes['customer_id'] ?? null;

        if ($customerId !== null && Customer::query()->findOrFail($customerId)->isArchived()) {
            throw InvalidTask::customerArchived();
        }

        $task = new Task([
            'title' => $attributes['title'],
            'notes' => filled($attributes['notes'] ?? null) ? $attributes['notes'] : null,
            'customer_id' => $customerId,
            'assigned_to' => $assignee->id,
            'due_date' => $attributes['due_date'] ?? null,
        ]);
        $task->forceFill(['created_by' => $actor->id])->save();

        if (! $assignee->is($actor)) {
            $this->sendTaskAssignedMail->handle($task, $actor);
        }

        return $task;
    }
}
