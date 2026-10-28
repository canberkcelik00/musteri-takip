<?php

namespace App\Mcp\Tools;

use App\Actions\Tasks\FilterTasks;
use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The "İşler" list for Claude, through the page's own FilterTasks and in its order: the user's open tasks by default.
 */
#[Description('List tasks: my open tasks by default (overdue and dated first, undated last); everyone\'s tasks with who=all; finished or all tasks with status; only overdue ones; one customer; a word in the title, notes or customer name.')]
#[IsReadOnly]
#[IsIdempotent]
class ListTasks extends BaseTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'who' => $schema->string()->enum(['mine', 'all'])->description('Defaults to mine.'),
            'status' => $schema->string()->enum(['open', 'done', 'all'])->description('Defaults to open.'),
            'only_overdue' => $schema->boolean()->description('Only open tasks past their due date.'),
            'customer_id' => $schema->integer()->description('Only this customer\'s tasks.'),
            'search' => $schema->string()->description('A word in the title, the notes or the customer name.'),
        ];
    }

    protected function permission(): ?Permission
    {
        return Permission::TasksManage;
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'who' => [Rule::in(['mine', 'all'])],
            'status' => [Rule::in(['open', 'done', 'all'])],
            'only_overdue' => ['boolean'],
            'customer_id' => ['integer'],
            'search' => ['string', 'max:100'],
        ]);

        $status = $input['status'] ?? 'open';

        $filtered = fn () => app(FilterTasks::class)->handle(
            assignee: ($input['who'] ?? 'mine') === 'all' ? null : $user,
            completed: match ($status) {
                'open' => false,
                'done' => true,
                default => null,
            },
            onlyOverdue: (bool) ($input['only_overdue'] ?? false),
            customerId: isset($input['customer_id']) ? (int) $input['customer_id'] : null,
            search: $input['search'] ?? '',
        );

        $query = $filtered()->with(['customer', 'assignee'])->limit(self::MAX_ROWS);

        if ($status === 'done') {
            $query->orderByDesc('completed_at')->orderByDesc('id');
        } else {
            $query->orderForList();
        }

        return Response::structured([
            'total' => $filtered()->count(),
            'tasks' => $query->get()->map(fn (Task $task): array => self::taskRow($task))->all(),
        ]);
    }
}
