<?php

namespace App\Enums;

/**
 * How one e-mail authentication record (SPF, DKIM, DMARC) of a domain reads (Faz 16, "E-posta" tab).
 */
enum EmailCheckStatus: string
{
    case Ok = 'ok';
    case Missing = 'missing';
    case Invalid = 'invalid';
    case Unreadable = 'unreadable';
}
