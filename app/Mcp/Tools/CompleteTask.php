<?php

namespace App\Mcp\Tools;

use App\Actions\Tasks\CompleteTask as CompleteTaskAction;
use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * The "İşler" list's "Bitti" for Claude, through the same CompleteTask: done by the token owner; a task already done
 * keeps who finished it and when.
 */
#[Description('Mark a task done (id from list-tasks). A task that is already done stays as it was.')]
#[IsDestructive(false)]
#[IsIdempotent]
class CompleteTask extends WriteTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The task id.')->required(),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::TasksManage;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
        ]);

        $task = app(CompleteTaskAction::class)->handle($user, Task::query()->findOrFail($input['task_id']));

        return Response::structured(['task' => self::taskRow($task->load(['customer', 'assignee']))]);
    }
}
