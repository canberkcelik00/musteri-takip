<?php

namespace App\Livewire\Pages\Settings;

use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\ResendInvitation;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\InvalidInvitation;
use App\Exceptions\InvalidUserChange;
use App\Exceptions\InvitationNotSent;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The users (docs/UI.md §5 Ayarlar > Kullanıcılar): name, e-mail, role and state, with the row actions — change the
 * role, send the invitation again, deactivate or reactivate. A user is never deleted (T-158). The invitation form is
 * a window.
 */
#[Layout('layouts.app')]
class Users extends Component
{
    use AuthorizesPermission;

    /**
     * A window invited someone: the list is read again.
     */
    #[On('users-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    public function changeRole(int $id, ChangeUserRole $changeRole): void
    {
        $user = User::query()->findOrFail($id);
        $role = $this->roleOf($user) === Role::Admin ? Role::Developer : Role::Admin;

        $this->attempt(fn () => $changeRole->handle($this->actor(), $user, $role), __('users.role_changed'));
    }

    public function resend(int $id, ResendInvitation $resendInvitation): void
    {
        $user = User::query()->findOrFail($id);

        $this->attempt(fn () => $resendInvitation->handle($user), __('users.invitation_sent', ['email' => $user->email]));
    }

    public function deactivate(int $id, DeactivateUser $deactivateUser): void
    {
        $user = User::query()->findOrFail($id);

        $this->attempt(fn () => $deactivateUser->handle($this->actor(), $user), __('users.deactivated'));
    }

    public function reactivate(int $id, ReactivateUser $reactivateUser): void
    {
        $user = User::query()->findOrFail($id);

        $this->attempt(fn () => $reactivateUser->handle($user), __('users.reactivated'));
    }

    private function attempt(callable $change, string $message): void
    {
        try {
            $change();
        } catch (InvalidUserChange|InvalidInvitation|InvitationNotSent $exception) {
            $this->dispatch('notify', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->dispatch('notify', message: $message);
    }

    private function roleOf(User $user): ?Role
    {
        return Role::tryFrom((string) $user->roles->first()?->getAttribute('name'));
    }

    protected function requiredPermission(): ?Permission
    {
        return Permission::UsersManage;
    }

    public function render(): View
    {
        /** @var Collection<int, User> $users */
        $users = User::query()->with('roles')->orderBy('name')->orderBy('id')->get();

        return view('livewire.pages.settings.users', [
            'users' => $users,
            'currentId' => Auth::id(),
        ])->title(__('users.plural'));
    }
}
