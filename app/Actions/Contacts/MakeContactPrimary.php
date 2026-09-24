<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class MakeContactPrimary
{
    /**
     * Make the contact its customer's only primary contact.
     */
    public function handle(Contact $contact): Contact
    {
        return DB::transaction(function () use ($contact): Contact {
            Contact::query()
                ->where('customer_id', $contact->customer_id)
                ->whereKeyNot($contact->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $contact->forceFill(['is_primary' => true])->save();

            return $contact;
        });
    }
}
