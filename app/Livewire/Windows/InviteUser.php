<?php

namespace App\Livewire\Windows;

use App\Actions\Users\InviteUser as InviteUserAction;
use App\Enums\Role;
use App\Exceptions\InvitationNotSent;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Invites a person: name, e-mail and role. The account is created without a password and the invitation link goes by
 * e-mail. When the e-mail cannot be sent the account is still there and the list offers to send it again.
 */
class InviteUser extends Component
{
    use AuthorizesPermission;

    public string $name = '';

    public string $email = '';

    public string $role = Role::Developer->value;

    public function save(InviteUserAction $inviteUser): void
    {
        $this->validate();

        try {
            $inviteUser->handle($this->name, $this->email, Role::from($this->role));
        } catch (InvitationNotSent $exception) {
            $this->dispatch('users-changed');
            $this->dispatch('close-window');
            $this->dispatch('notify', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->dispatch('users-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('users.invitation_sent', ['email' => $this->email]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('users.name'),
            'email' => __('users.email'),
            'role' => __('users.role'),
        ];
    }

    public function render(): View
    {
        return view('livewire.windows.invite-user', [
            'roles' => collect(Role::cases())->mapWithKeys(fn (Role $role): array => [$role->value => $role->getLabel()])->all(),
        ]);
    }
}
