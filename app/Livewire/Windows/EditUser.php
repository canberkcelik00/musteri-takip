<?php

namespace App\Livewire\Windows;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Role;
use App\Exceptions\InvalidUserChange;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Edits a user's name and role (docs/UI.md §5 Ayarlar > Kullanıcılar). The e-mail address is shown but never changed.
 * A user who has done nothing yet can be deleted from the named section at the bottom; one with records is
 * deactivated instead (T-158).
 */
class EditUser extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public int $userId = 0;

    public string $name = '';

    public string $role = '';

    public function mount(int $user): void
    {
        $record = User::query()->with('roles')->findOrFail($user);

        $this->userId = $record->id;
        $this->name = $record->name;
        $this->role = (string) $record->roles->first()?->getAttribute('name');
    }

    public function save(UpdateUser $updateUser): void
    {
        $this->validate();

        try {
            $updateUser->handle(
                $this->actor(),
                User::query()->findOrFail($this->userId),
                $this->name,
                RoleModel::query()->where('name', $this->role)->firstOrFail(),
            );
        } catch (InvalidUserChange $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->dispatch('users-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('users.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::exists('roles', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('users.name'),
            'role' => __('users.role'),
        ];
    }

    public function render(): View
    {
        $record = User::query()->findOrFail($this->userId);

        return view('livewire.windows.edit-user', [
            'record' => $record,
            'isSelf' => $this->actor()->is($record),
            'canDelete' => ! $this->actor()->is($record) && ! DeleteUser::hasRecords($record),
            'roles' => RoleModel::query()->orderBy('id')->pluck('name')->mapWithKeys(fn (string $name): array => [$name => Role::labelFor($name)])->all(),
        ]);
    }
}
