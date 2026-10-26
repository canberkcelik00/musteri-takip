<?php

namespace App\Actions\Tasks;

use App\Models\Task;

class ReopenTask
{
    public function handle(Task $task): Task
    {
        if ($task->isCompleted()) {
            $task->forceFill(['completed_at' => null, 'completed_by' => null])->save();
        }

        return $task;
    }
}
