<?php

namespace App\Livewire\Windows;

use App\Actions\Tasks\CleanPastedText;
use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\ReopenTask;
use App\Actions\Tasks\UpdateTask;
use App\Exceptions\InvalidTask;
use App\Exceptions\TaskMailNotSent;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or edits a task (docs/UI.md §5). Every change goes through the Actions in App\Actions\Tasks. A task that
 * exists also offers, in a named section, "Bitti" / "Yeniden aç" and the red, confirmed "Sil". The e-mail that tells
 * a new assignee is sent by the Action; when it fails the task stays saved and a yellow notice says so.
 */
class EditTask extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public ?int $taskId = null;

    public string $title = '';

    public string $notes = '';

    public string $customerId = '';

    public string $assignedTo = '';

    public string $dueDate = '';

    /**
     * `$pasted` is a text pasted with Ctrl+V outside any field (Faz 17, U-41): it only fills the notes, cleaned; the
     * title and the customer stay for the user.
     */
    public function mount(CleanPastedText $cleanPastedText, ?int $task = null, ?int $customer = null, ?string $pasted = null): void
    {
        $this->assignedTo = (string) $this->actor()->id;

        if ($task === null) {
            $this->customerId = $customer !== null ? (string) Customer::query()->findOrFail($customer)->id : '';
            $this->notes = $pasted !== null ? $cleanPastedText->handle($pasted) : '';

            return;
        }

        $record = Task::query()->findOrFail($task);

        $this->taskId = $record->id;
        $this->title = $record->title;
        $this->notes = (string) $record->notes;
        $this->customerId = (string) $record->customer_id;
        $this->assignedTo = (string) $record->assigned_to;
        $this->dueDate = (string) $record->due_date?->format('Y-m-d');
    }

    public function save(CreateTask $createTask, UpdateTask $updateTask): void
    {
        $this->validate();

        $attributes = [
            'title' => trim($this->title),
            'notes' => $this->notes,
            'customer_id' => $this->customerId === '' ? null : (int) $this->customerId,
            'assigned_to' => (int) $this->assignedTo,
            'due_date' => $this->dueDate === '' ? null : $this->dueDate,
        ];

        try {
            if ($this->taskId === null) {
                $createTask->handle($this->actor(), $attributes);
            } else {
                $updateTask->handle($this->actor(), Task::query()->findOrFail($this->taskId), $attributes);
            }
        } catch (InvalidTask $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        } catch (TaskMailNotSent $exception) {
            $this->dispatch('tasks-changed');
            $this->dispatch('close-window');
            $this->dispatch('notify', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->dispatch('tasks-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('tasks.updated'));
    }

    /**
     * A text pasted into the notes field is cleaned and added under the notes, so scattered messages gather in one task.
     */
    public function appendPasted(CleanPastedText $cleanPastedText, string $text): void
    {
        $cleaned = $cleanPastedText->handle($text);

        if ($cleaned === '') {
            return;
        }

        $notes = rtrim($this->notes);
        $this->notes = mb_substr($notes === '' ? $cleaned : $notes."\n".$cleaned, 0, CleanPastedText::MAX_LENGTH);
    }

    /**
     * The due-date pills: today, tomorrow or a week from today (Europe/Istanbul days); anything else is ignored.
     */
    public function pickDueDate(string $when): void
    {
        $this->dueDate = match ($when) {
            'today' => today()->format('Y-m-d'),
            'tomorrow' => today()->addDay()->format('Y-m-d'),
            'next-week' => today()->addDays(7)->format('Y-m-d'),
            default => $this->dueDate,
        };
    }

    public function complete(CompleteTask $completeTask): void
    {
        $completeTask->handle($this->actor(), Task::query()->findOrFail($this->taskId));

        $this->dispatch('tasks-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('tasks.completed'));
    }

    public function reopen(ReopenTask $reopenTask): void
    {
        $reopenTask->handle(Task::query()->findOrFail($this->taskId));

        $this->dispatch('tasks-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('tasks.reopened'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customerId' => ['nullable', 'integer', 'exists:customers,id'],
            'assignedTo' => ['required', 'integer', 'exists:users,id'],
            'dueDate' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => __('tasks.title'),
            'notes' => __('tasks.notes'),
            'customerId' => __('tasks.customer'),
            'assignedTo' => __('tasks.assignee'),
            'dueDate' => __('tasks.due_date'),
        ];
    }

    public function render(): View
    {
        $record = $this->taskId === null ? null : Task::query()->with(['creator', 'completer', 'customer', 'assignee'])->findOrFail($this->taskId);

        // Active users and unarchived customers can be picked; the ones a task already has stay listed.
        $assignees = User::query()->whereNull('deactivated_at')->orWhere('id', $record?->assigned_to)->orderBy('name')->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.($user->isActive() ? '' : ' '.__('tasks.inactive_suffix'))])->all();
        $customers = Customer::query()->notArchived()->orWhere('id', $record?->customer_id)->orderBy('short_name')->pluck('short_name', 'id')->all();

        return view('livewire.windows.edit-task', [
            'record' => $record,
            'assignees' => $assignees,
            'customers' => ['' => __('tasks.no_customer')] + $customers,
        ]);
    }
}
