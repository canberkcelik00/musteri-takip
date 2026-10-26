<?php

namespace App\Livewire\Pages\Tasks;

use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\ReopenTask;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\WithTable;
use App\Models\Customer;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The task list (docs/UI.md §5 "İşler"): the signed-in user's open tasks by default, the earliest due date first so
 * overdue ones lead and tasks without a date come last. "Herkes" shows everyone's tasks and the assignee column.
 *
 * @property-read array<int, string> $customerOptions
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesPermission;
    use WithTable;

    public const string WHO_MINE = 'mine';

    public const string WHO_ALL = 'all';

    public const string STATUS_OPEN = 'open';

    public const string STATUS_DONE = 'done';

    public const string STATUS_ALL = 'all';

    #[Url(except: 'mine')]
    public string $who = self::WHO_MINE;

    #[Url(except: 'open')]
    public string $status = self::STATUS_OPEN;

    #[Url(except: false)]
    public bool $onlyOverdue = false;

    #[Url(except: '')]
    public string $customer = '';

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [];
    }

    public function updated(string $name): void
    {
        if ($name !== 'search' && ! str_starts_with($name, 'paginators')) {
            $this->resetPage();
        }
    }

    public function toggleOverdue(): void
    {
        $this->onlyOverdue = ! $this->onlyOverdue;
        $this->resetPage();
    }

    public function complete(int $id, CompleteTask $completeTask): void
    {
        $completeTask->handle($this->actor(), Task::query()->findOrFail($id));

        $this->dispatch('notify', message: __('tasks.completed'));
    }

    public function reopen(int $id, ReopenTask $reopenTask): void
    {
        $reopenTask->handle(Task::query()->findOrFail($id));

        $this->dispatch('notify', message: __('tasks.reopened'));
    }

    /**
     * A task was changed in a window: the list is read again.
     */
    #[On('tasks-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * The customer filter's options, read once and kept until a customer is added or edited.
     *
     * @return array<int, string>
     */
    #[Computed(persist: true)]
    public function customerOptions(): array
    {
        return Customer::query()->orderBy('short_name')->pluck('short_name', 'id')->all();
    }

    #[On('customers-changed')]
    public function forgetCustomerOptions(): void
    {
        unset($this->customerOptions);
    }

    /**
     * @return Builder<Task>
     */
    private function filtered(): Builder
    {
        $query = Task::query();

        if ($this->who !== self::WHO_ALL) {
            $query->assignedTo($this->actor());
        }

        match ($this->status) {
            self::STATUS_OPEN => $query->open(),
            self::STATUS_DONE => $query->completed(),
            default => null,
        };

        if ($this->onlyOverdue) {
            $query->overdue();
        }

        if (ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customers) => $customers->where('short_name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    /**
     * @return LengthAwarePaginator<int, Task>
     */
    private function tasks(): LengthAwarePaginator
    {
        $query = $this->filtered()->with(['customer', 'assignee']);

        if ($this->status === self::STATUS_DONE) {
            $query->orderByDesc('completed_at')->orderByDesc('id');
        } else {
            $query->orderForList();
        }

        return $query->paginate($this->perPage());
    }

    protected function requiredPermission(): ?Permission
    {
        return Permission::TasksManage;
    }

    public function render(): View
    {
        return view('livewire.pages.tasks.index', [
            'tasks' => $this->tasks(),
            'customers' => $this->customerOptions,
            'statuses' => [
                self::STATUS_OPEN => __('tasks.status_open'),
                self::STATUS_DONE => __('tasks.status_done'),
                self::STATUS_ALL => __('tasks.status_all'),
            ],
        ])->title(__('tasks.plural'));
    }
}
