<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use Filament\Resources\Pages\ListRecords;

/**
 * "Borç ekle" moved to the topbar's "Yeni" menu (G-4.13), reachable from every page; this list
 * has no header action of its own.
 */
class ListCharges extends ListRecords
{
    use RefreshesOnPaymentOrChargeChange;

    protected static string $resource = ChargeResource::class;
}
