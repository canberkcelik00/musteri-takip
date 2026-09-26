<?php

namespace App\Filament\Actions;

use App\Actions\PaymentMethods\ArchivePaymentMethod;
use App\Exceptions\LastActivePaymentMethod;
use App\Models\PaymentMethod;

/**
 * Archives a payment method through ArchivePaymentMethod; the last active method is
 * left as it is and the reason is shown instead.
 */
class ArchivePaymentMethodAction extends ArchiveAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (PaymentMethod $record, ArchivePaymentMethod $archivePaymentMethod): void {
            try {
                $archivePaymentMethod->handle($record);
            } catch (LastActivePaymentMethod $exception) {
                $this->failureNotificationTitle($exception->getMessage());
                $this->failure();

                return;
            }

            $this->success();
        });
    }
}
