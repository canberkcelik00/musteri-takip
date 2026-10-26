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
     * Create a role (`$role` null) or change one: its name and its permissions. The administrator role is locked. A
     * built-in role keeps its stored name (the code finds it by it) and takes the typed name as its shown `label`. A
     * permission brings the one it requires (`money.manage` → `money.view`).
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

        $this->assertNameFree($name, $role);

        $values = collect($permissions)
            ->flatMap(fn (Permission $permission): array => array_filter([$permission, $permission->requires()]))
            ->map(fn (Permission $permission): string => $permission->value)
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($role, $name, $builtIn, $values): RoleModel {
            $role ??= RoleModel::query()->create(['name' => $name, 'guard_name' => 'web']);

            if ($builtIn) {
                $role->update(['label' => $name === Role::from($role->name)->getLabel() ? null : $name]);
            } elseif ($role->name !== $name) {
                $role->update(['name' => $name]);
            }

            $role->syncPermissions($values);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * No two roles share a shown name, and the administrator's names stay reserved so a role never looks like it. A
     * built-in role may keep its own current shown name.
     */
    private function assertNameFree(string $name, ?RoleModel $role): void
    {
        $lower = mb_strtolower($name);

        $reserved = collect([Role::Admin])->flatMap(fn (Role $case): array => [mb_strtolower($case->value), mb_strtolower($case->getLabel())]);

        $taken = RoleModel::query()
            ->when($role, fn ($query) => $query->whereKeyNot($role?->getKey()))
            ->get()
            ->contains(fn (RoleModel $other): bool => mb_strtolower($other->name) === $lower || mb_strtolower(Role::labelFor($other->name)) === $lower);

        $ownBuiltInName = $role !== null && Role::tryFrom($role->name) !== null && $lower === mb_strtolower($role->name);

        if (($taken || $reserved->contains($lower)) && ! $ownBuiltInName) {
            throw InvalidRole::nameTaken();
        }
    }
}
