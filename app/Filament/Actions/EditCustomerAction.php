<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Edits the customer's own details in a slide-over on the customer card, next to the billing
 * information it shows. Archive and delete stay at the bottom of the card, apart from this form.
 */
class EditCustomerAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editCustomer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('customers.edit'))
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(__('customers.edit_heading'))
            ->slideOver()
            ->fillForm(fn (Customer $record): array => $record->only(['short_name', 'legal_title', 'tax_office', 'tax_number', 'billing_address', 'notes']))
            ->schema([
                CustomerForm::customerSection(),
            ])
            ->action(function (array $data, Customer $record, Action $action): void {
                $record->update($data);

                $action->successNotificationTitle(__('customers.updated'))->success();
            });
    }
}
