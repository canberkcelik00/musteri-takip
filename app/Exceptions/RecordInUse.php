<?php

namespace App\Exceptions;

use DomainException;

class RecordInUse extends DomainException
{
    /**
     * @param  string  $reason  Key under `archive.in_use` in lang/tr/archive.php.
     */
    public function __construct(string $reason)
    {
        parent::__construct(__('archive.in_use.'.$reason));
    }
}
