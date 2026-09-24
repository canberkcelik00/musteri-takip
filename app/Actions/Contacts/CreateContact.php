<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateContact
{
    public function __construct(private MakeContactPrimary $makeContactPrimary) {}

    /**
     * Add a contact to the customer.
     *
     * The customer's first contact always becomes primary; a later contact
     * becomes primary only when asked, replacing the previous one.
     *
     * @param  array{name: string, title?: ?string, phone?: ?string, email?: ?string}  $attributes
     */
    public function handle(Customer $customer, array $attributes, bool $makePrimary = false): Contact
    {
        return DB::transaction(function () use ($customer, $attributes, $makePrimary): Contact {
            $isFirstContact = ! $customer->contacts()->lockForUpdate()->exists();

            $contact = $customer->contacts()->create($attributes);

            if ($isFirstContact || $makePrimary) {
                $contact = $this->makeContactPrimary->handle($contact);
            }

            return $contact;
        });
    }
}
