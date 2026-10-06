<?php

namespace App\Enums;

enum DeployResult: string
{
    case Deployed = 'deployed';
    case UpToDate = 'up_to_date';
    case NoRelease = 'no_release';
    case Busy = 'busy';

    /**
     * The HTTP status the deploy endpoint answers with.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Deployed, self::UpToDate => 200,
            self::NoRelease => 422,
            self::Busy => 409,
        };
    }

    public function message(): string
    {
        return __('deploy.'.$this->value);
    }
}
