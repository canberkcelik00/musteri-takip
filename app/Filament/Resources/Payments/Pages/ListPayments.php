<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

/**
 * "Ödeme al" moved to the topbar (G-4.13), reachable from every page; this list has no header
 * action of its own.
 */
class ListPayments extends ListRecords
{
    use RefreshesOnPaymentOrChargeChange;

    protected static string $resource = PaymentResource::class;
}
