<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Actions\ArchiveCustomerAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * The contacts and the other tables live on the customer card (ViewCustomer).
     */
    protected function getAllRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            ArchiveCustomerAction::make(),
            UnarchiveAction::make(),
        ];
    }
}
