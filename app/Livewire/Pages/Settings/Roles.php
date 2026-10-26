<?php

namespace App\Livewire\Pages\Settings;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * The roles (docs/UI.md §5 Ayarlar > Roller): each role with the number of permissions it holds and of the users who
 * have it. Adding and editing a role is a window.
 */
#[Layout('layouts.app')]
class Roles extends Component
{
    use AuthorizesPermission;

    /**
     * A window changed a role: the list is read again.
     */
    #[On('roles-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    protected function requiredPermission(): ?Permission
    {
        return Permission::UsersManage;
    }

    public function render(): View
    {
        /** @var Collection<int, RoleModel> $roles */
        $roles = RoleModel::query()->withCount(['permissions', 'users'])->orderBy('id')->get();

        return view('livewire.pages.settings.roles', ['roles' => $roles])->title(__('roles.plural'));
    }
}
