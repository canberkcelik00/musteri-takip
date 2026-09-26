<?php

namespace App\Actions\PaymentMethods;

use App\Exceptions\LastActivePaymentMethod;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class ArchivePaymentMethod
{
    /**
     * Archive a payment method while at least one other stays active; the payment form
     * would otherwise have no method to choose. An archived method is left as it is.
     *
     * @throws LastActivePaymentMethod
     */
    public function handle(PaymentMethod $paymentMethod): void
    {
        DB::transaction(function () use ($paymentMethod): void {
            $activeIds = PaymentMethod::query()->notArchived()->lockForUpdate()->pluck('id');

            if (! $activeIds->contains($paymentMethod->id)) {
                return;
            }

            if ($activeIds->count() === 1) {
                throw new LastActivePaymentMethod;
            }

            $paymentMethod->archive();
        });
    }
}
