<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Actions\ArchiveCustomerAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ArchiveCustomerAction::make(),
            UnarchiveAction::make(),
        ];
    }
}
