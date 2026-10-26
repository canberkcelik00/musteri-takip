<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The "task assigned" e-mail could not be sent; the task is saved all the same.
 */
class TaskMailNotSent extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(__('tasks.mail_not_sent', ['message' => $previous->getMessage()]), 0, $previous);
    }
}
