<?php

namespace App\Livewire\Windows;

use App\Actions\Roles\SaveRole;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\InvalidRole;
use App\Livewire\Concerns\AuthorizesPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Adds a role or edits one: its name and its permission boxes (docs/UI.md §5 Ayarlar > Roller). The administrator role
 * is shown locked with every box ticked; a built-in role keeps its name; a role nobody holds can be deleted from the
 * named section at the bottom.
 */
class EditRole extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public int $roleId = 0;

    public string $name = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount(?int $role = null): void
    {
        if ($role === null) {
            return;
        }

        $record = RoleModel::query()->with('permissions')->findOrFail($role);

        $this->roleId = $record->id;
        $this->name = Role::labelFor($record->name);
        $this->permissions = $record->permissions->pluck('name')->values()->all();
    }

    public function save(SaveRole $saveRole): void
    {
        $this->validate();

        try {
            $saveRole->handle(
                $this->roleId === 0 ? null : RoleModel::query()->findOrFail($this->roleId),
                $this->name,
                array_map(fn (string $value): Permission => Permission::from($value), $this->permissions),
            );
        } catch (InvalidRole $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->dispatch('roles-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('roles.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('roles.name'),
            'permissions' => __('roles.permissions'),
        ];
    }

    public function render(): View
    {
        $record = $this->roleId === 0 ? null : RoleModel::query()->withCount('users')->findOrFail($this->roleId);
        $locked = $record?->name === Role::Admin->value;

        return view('livewire.windows.edit-role', [
            'record' => $record,
            'locked' => $locked,
            'builtIn' => $record !== null && Role::tryFrom($record->name) !== null,
            'canDelete' => $record !== null && Role::tryFrom($record->name) === null && $record->getAttribute('users_count') === 0,
            'groups' => collect(Permission::cases())->groupBy(fn (Permission $permission): string => $permission->group()),
        ]);
    }
}
