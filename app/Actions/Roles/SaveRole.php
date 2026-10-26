<?php

namespace App\Actions\Roles;

use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\InvalidRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class SaveRole
{
    /**
     * Create a role (`$role` null) or change one: its name and its permissions. The administrator role is locked and a
     * built-in role keeps its name. A permission brings the one it requires (`money.manage` → `money.view`).
     *
     * @param  list<Permission>  $permissions
     *
     * @throws InvalidRole
     */
    public function handle(?RoleModel $role, string $name, array $permissions): RoleModel
    {
        $name = trim($name);

        if ($role?->name === Role::Admin->value) {
            throw InvalidRole::locked();
        }

        $builtIn = $role !== null && Role::tryFrom($role->name) !== null;

        if (! $builtIn) {
            $this->assertNameFree($name, $role);
        }

        $values = collect($permissions)
            ->flatMap(fn (Permission $permission): array => array_filter([$permission, $permission->requires()]))
            ->map(fn (Permission $permission): string => $permission->value)
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($role, $name, $builtIn, $values): RoleModel {
            $role ??= RoleModel::query()->create(['name' => $name, 'guard_name' => 'web']);

            if (! $builtIn && $role->name !== $name) {
                $role->update(['name' => $name]);
            }

            $role->syncPermissions($values);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * The built-in names and their shown names are reserved so a custom role never looks like a built-in one.
     */
    private function assertNameFree(string $name, ?RoleModel $role): void
    {
        $reserved = collect(Role::cases())->flatMap(fn (Role $case): array => [mb_strtolower($case->value), mb_strtolower($case->getLabel())]);

        $taken = RoleModel::query()->where('name', $name)->when($role, fn ($query) => $query->whereKeyNot($role?->getKey()))->exists();

        if ($taken || $reserved->contains(mb_strtolower($name))) {
            throw InvalidRole::nameTaken();
        }
    }
}
