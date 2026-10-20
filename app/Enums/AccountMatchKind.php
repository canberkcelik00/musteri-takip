<?php

namespace App\Enums;

enum AccountMatchKind: string
{
    case AlreadyLinked = 'already_linked';
    case Link = 'link';
    case New = 'new';
}
