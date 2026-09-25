<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Customers\CreateCustomer as CreateCustomerAction;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $contact = $data['contact'];

        unset($data['contact']);

        return app(CreateCustomerAction::class)->handle($data, $contact);
    }
}
