<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidPaymentAllocation;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Support\Money;

/**
 * @param  array<string, mixed>  $attributes
 */
function openChargeForPayment(Customer $customer, int $amount, string $dueDate, array $attributes = []): Charge
{
    return Charge::factory()->for($customer)->create([
        'amount' => $amount,
        'due_date' => $dueDate,
        ...$attributes,
    ]);
}

/**
 * @param  array<int, int>  $manualAllocations
 */
function recordPaymentFor(Customer $customer, int $amount, array $manualAllocations = [], Currency $currency = Currency::Try): Payment
{
    return app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => $amount,
        'currency' => $currency,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ], $manualAllocations);
}

/**
 * @return array<int, int>
 */
function allocationAmountsOf(Payment $payment): array
{
    return $payment->allocations->mapWithKeys(fn ($allocation) => [$allocation->charge_id => $allocation->amount])->all();
}

describe('automatic allocation', function () {
    it('closes a charge paid in full', function () {
        $customer = Customer::factory()->create();
        $charge = openChargeForPayment($customer, 400000, '2026-03-01');

        $payment = recordPaymentFor($customer, 400000);

        expect(allocationAmountsOf($payment))->toBe([$charge->id => 400000])
            ->and($charge->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($charge->fresh()->remainingAmount())->toBe(0)
            ->and($customer->creditBalances())->toBe([]);
    });

    it('leaves a partially paid charge open', function () {
        $customer = Customer::factory()->create();
        $charge = openChargeForPayment($customer, 400000, '2026-03-01');

        recordPaymentFor($customer, 150000);

        $charge->refresh();

        expect($charge->status)->toBe(ChargeStatus::Open)
            ->and($charge->isPartiallyPaid())->toBeTrue()
            ->and($charge->remainingAmount())->toBe(250000)
            ->and($customer->creditBalances())->toBe([]);
    });

    it('closes all open charges and leaves the overpayment as credit balance', function () {
        $customer = Customer::factory()->create();
        $hosting = openChargeForPayment($customer, 150000, '2026-03-01');
        $domain = openChargeForPayment($customer, 40000, '2026-04-01');

        recordPaymentFor($customer, 250000);

        expect($hosting->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($domain->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($customer->creditBalances())->toEqual(['TRY' => new Money(60000, Currency::Try)]);
    });

    it('allocates to the oldest due date first', function () {
        $customer = Customer::factory()->create();
        $domain = openChargeForPayment($customer, 40000, '2026-04-01');
        $hosting = openChargeForPayment($customer, 150000, '2026-03-01');

        $payment = recordPaymentFor($customer, 100000);

        expect(allocationAmountsOf($payment))->toBe([$hosting->id => 100000])
            ->and($hosting->fresh()->remainingAmount())->toBe(50000)
            ->and($domain->fresh()->remainingAmount())->toBe(40000);
    });

    it('allocates charges with the same due date in creation order', function () {
        $customer = Customer::factory()->create();
        $first = openChargeForPayment($customer, 50000, '2026-03-01');
        $second = openChargeForPayment($customer, 50000, '2026-03-01');

        $payment = recordPaymentFor($customer, 70000);

        expect(allocationAmountsOf($payment))->toEqual([$first->id => 50000, $second->id => 20000]);
    });

    it('allocates to charges that are not due yet', function () {
        $this->travelTo('2027-01-30');
        $customer = Customer::factory()->create();
        $renewal = openChargeForPayment($customer, 400000, '2027-03-01');

        recordPaymentFor($customer, 400000);

        expect($renewal->fresh()->status)->toBe(ChargeStatus::Paid);
    });

    it('allocates only the remaining amount of a partially paid charge', function () {
        $customer = Customer::factory()->create();
        $charge = openChargeForPayment($customer, 400000, '2026-03-01');
        recordPaymentFor($customer, 100000);

        $payment = recordPaymentFor($customer, 500000);

        expect(allocationAmountsOf($payment))->toBe([$charge->id => 300000])
            ->and($charge->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($customer->creditBalances())->toEqual(['TRY' => new Money(200000, Currency::Try)]);
    });

    it('does not allocate to a charge in another currency', function () {
        $customer = Customer::factory()->create();
        $usdCharge = openChargeForPayment($customer, 5000, '2026-01-01', ['currency' => Currency::Usd]);

        $payment = recordPaymentFor($customer, 100000);

        expect($payment->allocations)->toBeEmpty()
            ->and($usdCharge->fresh()->status)->toBe(ChargeStatus::Open)
            ->and($usdCharge->fresh()->remainingAmount())->toBe(5000)
            ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
    });

    it('skips paid and cancelled charges and charges of other customers', function () {
        $customer = Customer::factory()->create();
        $paid = openChargeForPayment($customer, 10000, '2026-01-01', ['status' => ChargeStatus::Paid]);
        $cancelled = Charge::factory()->for($customer)->cancelled()->create(['amount' => 10000, 'due_date' => '2026-01-01']);
        $otherCustomersCharge = openChargeForPayment(Customer::factory()->create(), 10000, '2026-01-01');
        $open = openChargeForPayment($customer, 10000, '2026-06-01');

        $payment = recordPaymentFor($customer, 30000);

        expect(allocationAmountsOf($payment))->toBe([$open->id => 10000])
            ->and($paid->allocations()->exists())->toBeFalse()
            ->and($cancelled->allocations()->exists())->toBeFalse()
            ->and($otherCustomersCharge->allocations()->exists())->toBeFalse()
            ->and($customer->creditBalances())->toEqual(['TRY' => new Money(20000, Currency::Try)]);
    });
});

describe('manual allocation', function () {
    it('applies the given lines first and allocates the rest to the oldest other charge', function () {
        $customer = Customer::factory()->create();
        $hosting = openChargeForPayment($customer, 150000, '2026-03-01');
        $domain = openChargeForPayment($customer, 40000, '2026-04-01');

        $payment = recordPaymentFor($customer, 100000, [$domain->id => 40000]);

        expect(allocationAmountsOf($payment))->toEqual([$domain->id => 40000, $hosting->id => 60000])
            ->and($domain->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($hosting->fresh()->remainingAmount())->toBe(90000)
            ->and($customer->creditBalances())->toBe([]);
    });

    it('fills what is left on a manually allocated charge before leaving credit balance', function () {
        $customer = Customer::factory()->create();
        $domain = openChargeForPayment($customer, 40000, '2026-04-01');

        $payment = recordPaymentFor($customer, 100000, [$domain->id => 10000]);

        expect(allocationAmountsOf($payment))->toBe([$domain->id => 40000])
            ->and($domain->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($customer->creditBalances())->toEqual(['TRY' => new Money(60000, Currency::Try)]);
    });

    it('leaves credit balance only after every open charge in the currency is closed', function () {
        $customer = Customer::factory()->create();
        $hosting = openChargeForPayment($customer, 150000, '2026-03-01');
        $domain = openChargeForPayment($customer, 40000, '2026-04-01');
        $usdCharge = openChargeForPayment($customer, 5000, '2026-01-01', ['currency' => Currency::Usd]);

        recordPaymentFor($customer, 200000, [$domain->id => 40000]);

        expect($hosting->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($domain->fresh()->status)->toBe(ChargeStatus::Paid)
            ->and($usdCharge->fresh()->status)->toBe(ChargeStatus::Open)
            ->and($customer->creditBalances())->toEqual(['TRY' => new Money(10000, Currency::Try)]);
    });

    it('rejects an invalid allocation without recording anything', function (Closure $setUp, string $message) {
        $customer = Customer::factory()->create();
        openChargeForPayment($customer, 50000, '2026-02-01');
        [$amount, $manualAllocations] = $setUp($customer);
        $paymentsBefore = Payment::query()->count();

        expect(fn () => recordPaymentFor($customer, $amount, $manualAllocations))
            ->toThrow(InvalidPaymentAllocation::class, $message);

        expect(Payment::query()->count())->toBe($paymentsBefore)
            ->and(PaymentAllocation::query()->exists())->toBeFalse();
    })->with([
        'charge of another customer' => [
            fn (Customer $customer) => [10000, [openChargeForPayment(Customer::factory()->create(), 10000, '2026-03-01')->id => 10000]],
            'Ödeme yalnızca aynı müşterinin borçlarına dağıtılabilir.',
        ],
        'charge in another currency' => [
            fn (Customer $customer) => [10000, [openChargeForPayment($customer, 10000, '2026-03-01', ['currency' => Currency::Usd])->id => 10000]],
            'Ödeme yalnızca aynı para birimindeki borçlara dağıtılabilir.',
        ],
        'more than the charge amount' => [
            fn (Customer $customer) => [20000, [openChargeForPayment($customer, 10000, '2026-03-01')->id => 10001]],
            'Bir borca kalan tutarından fazla dağıtılamaz.',
        ],
        'more than the payment amount' => [
            fn (Customer $customer) => [10000, [openChargeForPayment($customer, 20000, '2026-03-01')->id => 10001]],
            'Dağıtımların toplamı ödeme tutarını aşamaz.',
        ],
        'zero amount' => [
            fn (Customer $customer) => [10000, [openChargeForPayment($customer, 10000, '2026-03-01')->id => 0]],
            'Tutar sıfırdan büyük olmalıdır.',
        ],
        'negative amount' => [
            fn (Customer $customer) => [10000, [openChargeForPayment($customer, 10000, '2026-03-01')->id => -100]],
            'Tutar sıfırdan büyük olmalıdır.',
        ],
        'paid charge' => [
            fn (Customer $customer) => [10000, [openChargeForPayment($customer, 10000, '2026-03-01', ['status' => ChargeStatus::Paid])->id => 10000]],
            'Ödeme yalnızca açık borçlara dağıtılabilir.',
        ],
        'cancelled charge' => [
            fn (Customer $customer) => [10000, [Charge::factory()->for($customer)->cancelled()->create(['amount' => 10000])->id => 10000]],
            'Ödeme yalnızca açık borçlara dağıtılabilir.',
        ],
    ]);

    it('rejects more than what is left on a partially paid charge', function () {
        $customer = Customer::factory()->create();
        $charge = openChargeForPayment($customer, 40000, '2026-03-01');
        recordPaymentFor($customer, 30000);

        expect(fn () => recordPaymentFor($customer, 20000, [$charge->id => 10001]))
            ->toThrow(InvalidPaymentAllocation::class, 'Bir borca kalan tutarından fazla dağıtılamaz.');

        expect($customer->payments()->count())->toBe(1)
            ->and($charge->fresh()->remainingAmount())->toBe(10000);
    });

    it('rejects allocations whose total exceeds the payment amount', function () {
        $customer = Customer::factory()->create();
        $first = openChargeForPayment($customer, 30000, '2026-03-01');
        $second = openChargeForPayment($customer, 30000, '2026-04-01');

        expect(fn () => recordPaymentFor($customer, 50000, [$first->id => 30000, $second->id => 20001]))
            ->toThrow(InvalidPaymentAllocation::class, 'Dağıtımların toplamı ödeme tutarını aşamaz.');

        expect($customer->payments()->exists())->toBeFalse();
    });
});
