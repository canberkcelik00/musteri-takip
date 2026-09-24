<?php

namespace App\Exceptions;

use DomainException;

class PrimaryContactCannotBeDeleted extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('contacts.primary_cannot_be_deleted'));
    }
}
