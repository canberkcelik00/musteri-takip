<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Charges come only from the rules (G-5B.3 removed manual charges); this list has no header
 * action of its own.
 */
class ListCharges extends ListRecords
{
    use RefreshesOnPaymentOrChargeChange;

    protected static string $resource = ChargeResource::class;
}
