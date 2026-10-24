<?php

namespace App\Enums;

/**
 * How often the digest e-mail is sent: every day, or once a month on the configured day.
 */
enum DigestFrequency: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return __('enums.digest_frequency.'.$this->value);
    }
}
