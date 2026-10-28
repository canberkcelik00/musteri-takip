<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The task list's filters, shared by the "İşler" page and Claude's list-tasks tool (G-18.2) so both select the same
 * tasks for the same choices. `$assignee` null means everyone's tasks; `$completed` null means open and finished
 * ones. The caller adds the order, the eager loads and the page.
 */
class FilterTasks
{
    /**
     * @return Builder<Task>
     */
    public function handle(
        ?User $assignee = null,
        ?bool $completed = false,
        bool $onlyOverdue = false,
        ?int $customerId = null,
        string $search = '',
    ): Builder {
        $query = Task::query();

        if ($assignee !== null) {
            $query->assignedTo($assignee);
        }

        match ($completed) {
            false => $query->open(),
            true => $query->completed(),
            null => null,
        };

        if ($onlyOverdue) {
            $query->overdue();
        }

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        $term = trim($search);

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customers) => $customers->where('short_name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }
}
