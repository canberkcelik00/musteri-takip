<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use RefreshesOnPaymentOrChargeChange;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->color('gray'),
        ];
    }
}
