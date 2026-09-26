<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Resources\Charges\ChargeResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListCharges extends ListRecords
{
    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateManualChargeAction::make(),
        ];
    }

    /**
     * The list is rendered again when the event arrives; the empty method is enough.
     */
    #[On(CreateManualChargeAction::CHARGES_CHANGED_EVENT)]
    public function refreshAfterChargeChange(): void {}
}
