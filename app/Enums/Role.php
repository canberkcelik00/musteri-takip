<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Developer = 'developer';

    public function getLabel(): string
    {
        return __('enums.role.'.$this->value);
    }

    /**
     * The permissions the role holds. Widening a role's rights later means editing this list
     * only; the next deploy syncs it.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Developer => [Permission::CustomersManage, Permission::ServicesView],
        };
    }
}
