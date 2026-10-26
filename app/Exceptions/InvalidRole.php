<?php

namespace App\Exceptions;

use DomainException;

/**
 * A role change the rules forbid (locked or built-in role, name taken, role still in use).
 */
class InvalidRole extends DomainException
{
    public static function locked(): self
    {
        return new self(__('roles.locked'));
    }

    public static function builtIn(): self
    {
        return new self(__('roles.built_in'));
    }

    public static function nameTaken(): self
    {
        return new self(__('roles.name_taken'));
    }

    public static function inUse(): self
    {
        return new self(__('roles.in_use'));
    }
}
