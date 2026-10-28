<?php

namespace App\Mcp\Tools;

use App\Actions\Tasks\CreateTask as CreateTaskAction;
use App\Enums\Permission;
use App\Exceptions\InvalidTask;
use App\Exceptions\TaskMailNotSent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

/**
 * The task window's "add" for Claude, through the same CreateTask: the token owner creates it, it goes to them unless
 * assigned to someone else, who is e-mailed. A failed e-mail leaves the task saved and comes back as a warning, not
 * as an error, so Claude does not add it twice.
 */
#[Description('Add a task. It is assigned to me unless assigned_to names another active user, who is then e-mailed. A customer (id from search-customers) is optional but cannot be archived.')]
#[IsDestructive(false)]
class CreateTask extends WriteTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $users = User::query()->whereNull('deactivated_at')->orderBy('name')->get()
            ->map(fn (User $user): string => $user->id.' = '.$user->name)->implode(', ');

        return [
            'title' => $schema->string()->description('What is to be done, at most 255 characters.')->required(),
            'notes' => $schema->string()->description('Details, at most 5000 characters.'),
            'customer_id' => $schema->integer()->description('The customer the task is about.'),
            'assigned_to' => $schema->integer()->description('Who does it; defaults to me. Active users: '.$users.'.'),
            'due_date' => $schema->string()->description('Due date as Y-m-d.'),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::TasksManage;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $warning = null;

        try {
            $task = app(CreateTaskAction::class)->handle($user, [...$input, 'title' => trim($input['title'])]);
        } catch (InvalidTask $exception) {
            return Response::error($exception->getMessage());
        } catch (TaskMailNotSent $exception) {
            $task = null;
            $warning = $exception->getMessage();
        }

        return Response::structured([
            'task' => $task === null ? null : self::taskRow($task->load(['customer', 'assignee'])),
            'warning' => $warning,
        ]);
    }
}
