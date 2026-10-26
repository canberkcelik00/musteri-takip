<?php

namespace App\Enums;

use Spatie\Permission\Models\Role as RoleModel;

enum Role: string
{
    case Admin = 'admin';
    case Developer = 'developer';

    public function getLabel(): string
    {
        return __('enums.role.'.$this->value);
    }

    /**
     * The shown name of a role by its stored name: a built-in role shows the name the administrator gave it (`label`)
     * or else its translation; a role the administrator made is shown as typed.
     */
    public static function labelFor(string $name): string
    {
        $builtIn = self::tryFrom($name);

        if ($builtIn === null) {
            return $name;
        }

        $label = RoleModel::query()->where('name', $name)->value('label');

        return is_string($label) && $label !== '' ? $label : $builtIn->getLabel();
    }

    /**
     * The permissions the role starts with. The administrator always holds them all; the developer's list is only
     * the first default, the roles screen changes it afterwards (T-165).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Developer => [Permission::CustomersManage, Permission::ServicesView, Permission::TasksManage],
        };
    }
}
