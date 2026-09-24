<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

function seededCustomer(string $shortName): Customer
{
    return Customer::query()->where('short_name', $shortName)->firstOrFail();
}

it('covers every sample scenario', function () {
    $this->seed();

    $charges = Charge::query()->withSum('allocations', 'amount')->get();

    expect(CustomerService::query()->where('is_free', true)->exists())->toBeTrue()
        ->and(CustomerService::query()->where('status', CustomerServiceStatus::Cancelled)->exists())->toBeTrue()
        ->and(CustomerService::query()->whereNotNull('package_id')->exists())->toBeTrue()
        ->and(CatalogService::query()->onlyArchived()->exists())->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->status === ChargeStatus::Paid))->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->status === ChargeStatus::Cancelled))->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->isPartiallyPaid()))->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->isOverdue()))->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->status === ChargeStatus::Open && $charge->due_date->isFuture()))->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->currency === Currency::Usd))->toBeTrue()
        ->and($charges->contains(fn (Charge $charge) => $charge->customer_service_id === null))->toBeTrue()
        ->and(seededCustomer('Anadolu Gıda')->creditBalances())->toEqual([
            'TRY' => new Money(Money::parseAmount('500'), Currency::Try),
        ])
        ->and(seededCustomer('Ege Yazılım')->creditBalances())->toEqual([
            'USD' => new Money(Money::parseAmount('21'), Currency::Usd),
        ]);
});

it('keeps the sample data consistent', function () {
    $this->seed();

    Charge::query()->withSum('allocations', 'amount')->get()->each(function (Charge $charge) {
        match ($charge->status) {
            ChargeStatus::Paid => expect($charge->remainingAmount())->toBe(0),
            ChargeStatus::Open => expect($charge->remainingAmount())->toBeGreaterThan(0),
            ChargeStatus::Cancelled => expect($charge->allocatedAmount())->toBe(0),
        };
    });

    PaymentAllocation::query()->with(['payment', 'charge'])->get()->each(function (PaymentAllocation $allocation) {
        expect($allocation->payment->customer_id)->toBe($allocation->charge->customer_id)
            ->and($allocation->payment->currency)->toBe($allocation->charge->currency);
    });

    Payment::query()->withSum('allocations', 'amount')->get()->each(function (Payment $payment) {
        expect((int) $payment->allocations_sum_amount)->toBeLessThanOrEqual($payment->amount);
    });

    Customer::query()->withCount(['contacts as primary_contacts_count' => fn ($query) => $query->where('is_primary', true)])
        ->get()
        ->each(fn (Customer $customer) => expect($customer->primary_contacts_count)->toBe(1));
});

it('seeds an open charge due today that is not overdue', function () {
    $this->seed();

    $charge = seededCustomer('Kaya Matbaa')->charges()->sole();

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->due_date->toDateString())->toBe(today()->toDateString())
        ->and($charge->remainingAmount())->toBe($charge->amount)
        ->and($charge->isOverdue())->toBeFalse();
});

it('seeds an archived customer with its past service and paid charge', function () {
    $this->seed();

    $customer = seededCustomer('Güneş Tekstil');
    $service = $customer->customerServices()->sole();
    $charge = $customer->charges()->sole();

    expect($customer->isArchived())->toBeTrue()
        ->and(Customer::query()->notArchived()->whereKey($customer->id)->exists())->toBeFalse()
        ->and($service->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($service->end_date->isPast())->toBeTrue()
        ->and($charge->status)->toBe(ChargeStatus::Paid)
        ->and($charge->remainingAmount())->toBe(0);
});

it('seeds a free service ending in less than 30 days without charges', function () {
    $this->seed();

    $customer = seededCustomer('Karadeniz Doğa Derneği');
    $service = $customer->customerServices()->sole();

    expect($service->is_free)->toBeTrue()
        ->and($service->status)->toBe(CustomerServiceStatus::Active)
        ->and($service->end_date->isAfter(today()))->toBeTrue()
        ->and($service->end_date->isBefore(today()->addDays(30)))->toBeTrue()
        ->and($customer->charges()->exists())->toBeFalse();
});

it('seeds the Logi scenario with the credit applied to the renewal charge', function () {
    $this->seed();

    $customer = seededCustomer('Logi');
    $service = $customer->customerServices()->with('package')->sole();
    $currentCharge = $customer->charges()->where('period_start', $service->start_date)->sole();
    $renewalCharge = $customer->charges()->where('period_start', $service->end_date)->sole();

    expect($service->package?->name)->toBe('Kurumsal Paket')
        ->and($service->price)->toBe(Money::parseAmount('4.000'))
        ->and($service->end_date->isAfter(today()))->toBeTrue()
        ->and($service->end_date->isBefore(today()->addDays(30)))->toBeTrue()
        ->and($currentCharge->status)->toBe(ChargeStatus::Paid)
        ->and($currentCharge->remainingAmount())->toBe(0)
        ->and($renewalCharge->status)->toBe(ChargeStatus::Open)
        ->and($renewalCharge->amount)->toBe(Money::parseAmount('4.000'))
        ->and($renewalCharge->due_date->toDateString())->toBe($service->end_date->toDateString())
        ->and($renewalCharge->allocatedAmount())->toBe(Money::parseAmount('1.000'))
        ->and($renewalCharge->remainingAmount())->toBe(Money::parseAmount('3.000'))
        ->and($customer->payments()->sole()->amount)->toBe(Money::parseAmount('5.000'))
        ->and($customer->creditBalances())->toBe([]);
});

it('seeds without errors on edge dates', function (string $date) {
    $this->travelTo(Carbon::parse($date, config('app.timezone')));

    $this->seed();

    expect(seededCustomer('Kaya Matbaa')->charges()->sole()->isOverdue())->toBeFalse();
})->with(['2028-02-29', '2027-01-31', '2026-12-31']);

it('refuses to run in production before writing anything', function () {
    app()->detectEnvironment(fn () => 'production');

    $seeder = app(DatabaseSeeder::class)->setContainer(app());

    expect(fn () => $seeder->__invoke())->toThrow(RuntimeException::class);

    expect(User::query()->exists())->toBeFalse()
        ->and(Customer::query()->exists())->toBeFalse();
});
