<?php

namespace App\Livewire\Windows;

use App\Enums\Permission;
use App\Exceptions\CustomerHasActiveServices;
use App\Exceptions\InvalidQuote;
use App\Exceptions\InvalidRole;
use App\Exceptions\InvalidUserChange;
use App\Exceptions\LastActivePaymentMethod;
use App\Exceptions\PrimaryContactCannotBeDeleted;
use App\Exceptions\RecordInUse;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Support\Ui\Confirmations;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The one confirmation window: archive, unarchive and the deletions (see {@see Confirmations}). A refusal from the
 * Action stays in the window, at its top; otherwise the window closes, tells the result and lets the pages refresh.
 */
class Confirm extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public string $action = '';

    #[Locked]
    public int $id = 0;

    public function mount(string $action, int $id): void
    {
        $permission = Confirmations::permission($action);

        if ($permission !== null) {
            $this->authorizePermission($permission);
        }

        Confirmations::describe($action, $id);

        $this->action = $action;
        $this->id = $id;
    }

    public function confirm(): void
    {
        try {
            $result = Confirmations::run($this->action, $this->id);
        } catch (RecordInUse|CustomerHasActiveServices|PrimaryContactCannotBeDeleted|LastActivePaymentMethod|InvalidQuote|InvalidUserChange|InvalidRole $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->dispatch($result['event']);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: $result['message'], tone: $result['tone'] ?? 'success');

        if ($result['redirect'] !== null) {
            $this->redirect($result['redirect']);
        }
    }

    protected function requiredPermission(): ?Permission
    {
        return $this->action === '' ? null : Confirmations::permission($this->action);
    }

    public function render(): View
    {
        return view('livewire.windows.confirm', ['text' => Confirmations::describe($this->action, $this->id)]);
    }
}
