<?php

namespace App\Actions\PaymentMethods;

use App\Exceptions\LastActivePaymentMethod;
use App\Exceptions\RecordInUse;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class DeletePaymentMethod
{
    /**
     * Delete a payment method no payment uses; a used one is archived instead. Like archiving,
     * it never removes the last active method (the payment form would have none to choose).
     *
     * @throws RecordInUse
     * @throws LastActivePaymentMethod
     */
    public function handle(PaymentMethod $paymentMethod): void
    {
        DB::transaction(function () use ($paymentMethod): void {
            $activeIds = PaymentMethod::query()->notArchived()->lockForUpdate()->pluck('id');

            $locked = PaymentMethod::query()->lockForUpdate()->find($paymentMethod->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->isInUse()) {
                throw new RecordInUse('payment_method');
            }

            if ($activeIds->count() === 1 && $activeIds->contains($locked->id)) {
                throw new LastActivePaymentMethod;
            }

            $locked->delete();
        });
    }
}
