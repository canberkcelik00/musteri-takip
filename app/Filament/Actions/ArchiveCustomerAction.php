<?php

namespace App\Filament\Actions;

use App\Actions\Customers\ArchiveCustomer;
use App\Exceptions\CustomerHasActiveServices;
use App\Models\Customer;

/**
 * Archives a customer through ArchiveCustomer; a customer with an active service is
 * left as it is and the reason is shown instead.
 */
class ArchiveCustomerAction extends ArchiveAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (Customer $record, ArchiveCustomer $archiveCustomer): void {
            try {
                $archiveCustomer->handle($record);
            } catch (CustomerHasActiveServices $exception) {
                $this->failureNotificationTitle($exception->getMessage());
                $this->failure();

                return;
            }

            $this->success();
        });
    }
}
