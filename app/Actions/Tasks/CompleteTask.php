<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;

class CompleteTask
{
    /**
     * Mark the task done by `$actor`. A task already done keeps who finished it and when.
     */
    public function handle(User $actor, Task $task): Task
    {
        if (! $task->isCompleted()) {
            $task->forceFill(['completed_at' => now(), 'completed_by' => $actor->id])->save();
        }

        return $task;
    }
}
