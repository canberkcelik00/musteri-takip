<?php

use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\Payments\RecordPayment;
use App\Enums\AllocationSource;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Support\Money;

/**
 * @return array{0: string, 1: string, 2: CustomerServiceStatus}
 */
function logiServiceState(CustomerService $customerService): array
{
    $customerService->refresh();

    return [
        $customerService->start_date->toDateString(),
        $customerService->end_date->toDateString(),
        $customerService->status,
    ];
}

/**
 * @return array{amount: int, currency: Currency, due_date: string, period_start: string, period_end: string, status: ChargeStatus, allocated: int, remaining: int}
 */
function logiChargeState(Charge $charge): array
{
    $charge = Charge::query()->withSum('allocations', 'amount')->findOrFail($charge->id);

    return [
        'amount' => $charge->amount,
        'currency' => $charge->currency,
        'due_date' => $charge->due_date->toDateString(),
        'period_start' => $charge->period_start?->toDateString(),
        'period_end' => $charge->period_end?->toDateString(),
        'status' => $charge->status,
        'allocated' => $charge->allocatedAmount(),
        'remaining' => $charge->remainingAmount(),
    ];
}

it('runs the Logi scenario from assignment to cancellation', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => Money::parseAmount('4.000'), 'currency' => Currency::Try]);
    $package->catalogServices()->attach([
        CatalogService::factory()->ofType(ServiceType::Hosting)->create()->id,
        CatalogService::factory()->ofType(ServiceType::Domain)->create()->id,
        CatalogService::factory()->ofType(ServiceType::Maintenance)->create()->id,
    ]);

    // 1. Assigned for 4.000 TL on 01.03.2026; the first period's charge is due that day.
    $this->travelTo('2026-03-01 10:00:00');
    $service = app(AssignCustomerService::class)->handle($customer, $package, ['start_date' => '2026-03-01']);
    $firstCharge = $customer->charges()->sole();

    expect(logiServiceState($service))->toBe(['2026-03-01', '2027-03-01', CustomerServiceStatus::Active])
        ->and($service->items()->pluck('type')->all())->toBe([ServiceType::Hosting, ServiceType::Domain, ServiceType::Maintenance])
        ->and(logiChargeState($firstCharge))->toBe([
            'amount' => Money::parseAmount('4.000'),
            'currency' => Currency::Try,
            'due_date' => '2026-03-01',
            'period_start' => '2026-03-01',
            'period_end' => '2027-03-01',
            'status' => ChargeStatus::Open,
            'allocated' => 0,
            'remaining' => Money::parseAmount('4.000'),
        ]);

    // 2. 5.000 TL paid: 4.000 TL closes the charge, 1.000 TL stays as credit balance.
    $payment = app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => Money::parseAmount('5.000'),
        'currency' => Currency::Try,
        'paid_on' => '2026-03-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    expect($payment->allocations->pluck('amount', 'charge_id')->all())->toBe([$firstCharge->id => Money::parseAmount('4.000')])
        ->and(logiChargeState($firstCharge)['status'])->toBe(ChargeStatus::Paid)
        ->and(logiChargeState($firstCharge)['remaining'])->toBe(0)
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(Money::parseAmount('1.000'), Currency::Try)]);

    // 3. On 30.01.2027 (30 days before the end) the renewal charge is created and the credit applied.
    $this->travelTo('2027-01-29 23:59:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect($customer->charges()->count())->toBe(1);

    $this->travelTo('2027-01-30 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();
    $this->artisan('app:process-customer-services')->assertSuccessful();
    $renewalCharge = $customer->charges()->whereKeyNot($firstCharge->id)->sole();

    expect(logiChargeState($renewalCharge))->toBe([
        'amount' => Money::parseAmount('4.000'),
        'currency' => Currency::Try,
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
        'status' => ChargeStatus::Open,
        'allocated' => Money::parseAmount('1.000'),
        'remaining' => Money::parseAmount('3.000'),
    ])
        ->and($renewalCharge->allocations()->sole()->only(['payment_id', 'source']))->toBe(['payment_id' => $payment->id, 'source' => AllocationSource::CreditBalance])
        ->and($customer->creditBalances())->toBe([])
        ->and(logiServiceState($service))->toBe(['2026-03-01', '2027-03-01', CustomerServiceStatus::Active]);

    // 4. On 01.03.2027 the service moves to the period 01.03.2027 – 01.03.2028.
    $this->travelTo('2027-02-28 23:59:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(logiServiceState($service))->toBe(['2026-03-01', '2027-03-01', CustomerServiceStatus::Active]);

    $this->travelTo('2027-03-01 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(logiServiceState($service))->toBe(['2027-03-01', '2028-03-01', CustomerServiceStatus::Active])
        ->and($customer->charges()->count())->toBe(2)
        ->and(logiChargeState($renewalCharge)['remaining'])->toBe(Money::parseAmount('3.000'));

    // 5. Cancelled on 01.06.2027: the started period keeps its charge, nothing renews afterwards.
    $this->travelTo('2027-06-01 10:00:00');
    $cancelledCharges = app(CancelCustomerService::class)->handle($service, cancelNote: 'Müşteri hizmeti bıraktı.');

    expect($cancelledCharges)->toBeEmpty()
        ->and(logiServiceState($service))->toBe(['2027-03-01', '2028-03-01', CustomerServiceStatus::Cancelled])
        ->and($service->cancelled_on->toDateString())->toBe('2027-06-01')
        ->and(logiChargeState($renewalCharge)['status'])->toBe(ChargeStatus::Open)
        ->and(logiChargeState($renewalCharge)['remaining'])->toBe(Money::parseAmount('3.000'));

    foreach (['2028-01-31 09:00:00', '2028-03-01 09:00:00', '2028-06-01 09:00:00'] as $date) {
        $this->travelTo($date);
        $this->artisan('app:process-customer-services')->assertSuccessful();
    }

    expect(logiServiceState($service))->toBe(['2027-03-01', '2028-03-01', CustomerServiceStatus::Cancelled])
        ->and($customer->charges()->count())->toBe(2)
        ->and(logiChargeState($firstCharge)['status'])->toBe(ChargeStatus::Paid)
        ->and(logiChargeState($renewalCharge)['remaining'])->toBe(Money::parseAmount('3.000'));
});
