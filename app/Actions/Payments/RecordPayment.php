<?php

namespace App\Actions\Payments;

use App\Actions\Charges\SyncChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidPaymentAllocation;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordPayment
{
    public function __construct(
        private SuggestPaymentAllocations $suggestPaymentAllocations,
        private SyncChargeStatus $syncChargeStatus,
    ) {}

    /**
     * Record the customer's payment and allocate it to charges (see SuggestPaymentAllocations).
     * Whatever is not allocated stays as the customer's credit balance.
     *
     * @param  array{amount: int, currency: Currency, paid_on: CarbonInterface|string, payment_method_id: int, note?: ?string}  $attributes
     * @param  array<int, int>  $manualAllocations  Amounts in minor units keyed by charge id.
     *
     * @throws InvalidPaymentAllocation
     */
    public function handle(Customer $customer, array $attributes, array $manualAllocations = []): Payment
    {
        return DB::transaction(function () use ($customer, $attributes, $manualAllocations): Payment {
            $customer->charges()
                ->where('currency', $attributes['currency'])
                ->lockForUpdate()
                ->pluck('id');

            $allocations = $this->suggestPaymentAllocations->handle(
                $customer,
                $attributes['currency'],
                $attributes['amount'],
                $manualAllocations,
            );

            $payment = $customer->payments()->create($attributes);

            foreach ($allocations as $chargeId => $amount) {
                $payment->allocations()->create(['charge_id' => $chargeId, 'amount' => $amount]);
            }

            foreach (Charge::query()->findMany(array_keys($allocations)) as $charge) {
                $this->syncChargeStatus->handle($charge);
            }

            return $payment->load('allocations');
        });
    }
}
