<?php

namespace App\Actions\Customers;

use App\Actions\Contacts\CreateContact;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateCustomer
{
    public function __construct(private CreateContact $createContact) {}

    /**
     * Create a customer together with its first contact, which becomes the primary
     * contact. Both are written in one transaction, so a customer never exists without a contact.
     *
     * @param  array{short_name: string, legal_title?: ?string, tax_office?: ?string, tax_number?: ?string, billing_address?: ?string, notes?: ?string}  $attributes
     * @param  array{name: string, title?: ?string, phone?: ?string, email?: ?string}  $contact
     */
    public function handle(array $attributes, array $contact): Customer
    {
        return DB::transaction(function () use ($attributes, $contact): Customer {
            $customer = Customer::create($attributes);

            $this->createContact->handle($customer, $contact);

            return $customer;
        });
    }
}
