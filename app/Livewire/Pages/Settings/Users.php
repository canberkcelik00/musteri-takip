<?php

namespace App\Livewire\Pages\Settings;

use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\ResendInvitation;
use App\Enums\Permission;
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
 * The users (docs/UI.md §5 Ayarlar > Kullanıcılar): name, e-mail, role and state, with the row actions — edit (a
 * window: name, role, delete), send the invitation again, deactivate or reactivate. The invitation form is a window.
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
