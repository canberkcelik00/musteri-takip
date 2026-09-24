<?php

namespace App\Actions\Contacts;

use App\Exceptions\PrimaryContactCannotBeDeleted;
use App\Models\Contact;

class DeleteContact
{
    /**
     * Delete a contact that is not the customer's primary contact.
     *
     * @throws PrimaryContactCannotBeDeleted
     */
    public function handle(Contact $contact): void
    {
        if ($contact->is_primary) {
            throw new PrimaryContactCannotBeDeleted;
        }

        $contact->delete();
    }
}
