<?php

namespace App\Actions\Servers;

use App\Enums\AccountMatchKind;
use App\Models\CustomerServiceItem;

final readonly class AccountMatch
{
    public function __construct(
        public AccountMatchKind $kind,
        public ?CustomerServiceItem $item = null,
    ) {}
}
