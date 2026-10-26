<?php

namespace App\Actions\Tasks;

use App\Exceptions\TaskMailNotSent;
use App\Mail\TaskAssignedMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTaskAssignedMail
{
    /**
     * E-mail the assignee that `$assignedBy` gave them the task. The task is already saved when this runs.
     *
     * @throws TaskMailNotSent
     */
    public function handle(Task $task, User $assignedBy): void
    {
        $task->unsetRelation('assignee')->loadMissing(['assignee', 'customer']);

        try {
            Mail::to($task->assignee->email)->send(new TaskAssignedMail($task, $assignedBy));
        } catch (Throwable $exception) {
            throw new TaskMailNotSent($exception);
        }
    }
}
