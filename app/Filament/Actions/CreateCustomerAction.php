<?php

namespace App\Filament\Actions;

use App\Actions\Customers\CreateCustomer;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * The new-customer form in the form window: customer details and the first (primary) contact,
 * created together through CreateCustomer; afterwards the customer's card opens. Shared by the
 * customer list, its empty state and the topbar's "Yeni" menu, so the customer is created the
 * same way — and looks the same — everywhere.
 */
class CreateCustomerAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createCustomer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('customers.create'))
            ->icon(Heroicon::Plus)
            ->modalHeading(__('customers.create_heading'))
            ->model(Customer::class)
            ->schema([
                CustomerForm::customerSection(),
                CustomerForm::firstContactSection(),
            ])
            ->action(function (array $data, Action $action, CreateCustomer $createCustomer): void {
                $contact = $data['contact'];

                unset($data['contact']);

                $customer = $createCustomer->handle($data, $contact);

                $action
                    ->successNotificationTitle(__('customers.created'))
                    ->successRedirectUrl(CustomerResource::getUrl('view', ['record' => $customer]))
                    ->success();
            });
    }
}
