<?php

namespace App\Actions\Tasks;

use App\Exceptions\InvalidTask;
use App\Exceptions\TaskMailNotSent;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;

class UpdateTask
{
    public function __construct(private SendTaskAssignedMail $sendTaskAssignedMail) {}

    /**
     * Change a task. Only a changed assignee is checked (a task may stay with someone deactivated since) and, when the
     * new assignee is not `$actor`, e-mailed; a failed e-mail leaves the change saved. The same goes for the customer:
     * only moving the task to an archived customer is refused.
     *
     * @param  array{title: string, notes?: ?string, customer_id?: ?int, assigned_to: int, due_date?: mixed}  $attributes
     *
     * @throws InvalidTask
     * @throws TaskMailNotSent
     */
    public function handle(User $actor, Task $task, array $attributes): Task
    {
        $assigneeChanged = (int) $attributes['assigned_to'] !== $task->assigned_to;

        if ($assigneeChanged && ! User::query()->findOrFail($attributes['assigned_to'])->isActive()) {
            throw InvalidTask::assigneeInactive();
        }

        $customerId = $attributes['customer_id'] ?? null;

        if ($customerId !== null && $customerId !== $task->customer_id && Customer::query()->findOrFail($customerId)->isArchived()) {
            throw InvalidTask::customerArchived();
        }

        $task->fill([
            'title' => $attributes['title'],
            'notes' => filled($attributes['notes'] ?? null) ? $attributes['notes'] : null,
            'customer_id' => $customerId,
            'assigned_to' => $attributes['assigned_to'],
            'due_date' => $attributes['due_date'] ?? null,
        ])->save();

        if ($assigneeChanged && $task->assigned_to !== $actor->id) {
            $this->sendTaskAssignedMail->handle($task, $actor);
        }

        return $task;
    }
}
