<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Actions\ArchiveCustomerAction;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Livewire\Attributes\On;

/**
 * The customer card. The relation managers (contacts, services, charges, payments) are
 * stacked under the details instead of being put in tabs, so everything is on one page.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ArchiveCustomerAction::make(),
            UnarchiveAction::make(),
        ];
    }

    /**
     * Recording or deleting a payment changes the open debt and credit balance shown above the
     * tables; they are computed from the record, so a fresh record is enough.
     */
    #[On(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)]
    #[On(CreateManualChargeAction::CHARGES_CHANGED_EVENT)]
    public function refreshAfterPaymentChange(): void
    {
        $this->record->refresh();
    }

    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();

        return Group::make(
            collect($this->getCachedRelationManagers())
                ->map(fn (string $manager): Livewire => Livewire::make($manager, [
                    'ownerRecord' => $ownerRecord,
                    'pageClass' => static::class,
                ])->key($manager))
                ->all(),
        );
    }
}
