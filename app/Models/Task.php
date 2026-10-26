<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Open", "done", "overdue" and "due today" are never stored: they follow from `completed_at` and `due_date`.
 * `created_by`, `completed_by` and `completed_at` are not fillable; only the actions in App\Actions\Tasks set them.
 *
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 */
#[Fillable(['title', 'notes', 'customer_id', 'assigned_to', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('completed_at'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('completed_at'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function assignedTo(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('assigned_to'), $user->id);
    }

    /**
     * Open tasks whose due date is before today.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('completed_at'))
            ->whereDate($this->qualifyColumn('due_date'), '<', today());
    }

    /**
     * Open tasks due today.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function dueToday(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('completed_at'))
            ->whereDate($this->qualifyColumn('due_date'), today());
    }

    /**
     * The list order: the earliest due date first, so overdue tasks lead; tasks without a date come last.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function orderForList(Builder $query): void
    {
        $query->orderByRaw($this->qualifyColumn('due_date').' is null')
            ->orderBy($this->qualifyColumn('due_date'))
            ->orderBy($this->qualifyColumn('id'));
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function isDueToday(): bool
    {
        return ! $this->isCompleted() && $this->due_date !== null && $this->due_date->isSameDay(today());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
