<?php

use App\Actions\Today\SummarizeToday;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Customers\Show;
use App\Livewire\Pages\Today;
use App\Livewire\Windows\AssignService;
use App\Livewire\Windows\CancelService;
use App\Livewire\Windows\CreateCustomer;
use App\Livewire\Windows\EditCatalogService;
use App\Livewire\Windows\RecordPayment;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * What a charge looks like, for comparing a charge before and after a step.
 *
 * @return array{amount: int, status: ChargeStatus, allocated: int, due_date: string}
 */
function mvpChargeState(Charge $charge): array
{
    $charge = Charge::query()->withSum('allocations', 'amount')->findOrFail($charge->id);

    return [
        'amount' => $charge->amount,
        'status' => $charge->status,
        'allocated' => $charge->allocatedAmount(),
        'due_date' => $charge->due_date->toDateString(),
    ];
}

/**
 * The first number of "Bugün" (total receivable) as the page shows it.
 */
function mvpTotalReceivable(): string
{
    $totals = app(SummarizeToday::class)->handle()['total'];

    return $totals === [] ? '—' : array_values($totals)[0]->format();
}

it('runs the main flow through the screens: customer, service, payment, renewal, cancellation', function () {
    $this->travelTo('2026-03-01 10:00:00');

    // 1. A catalog service is added from the catalog list.
    Livewire::test(EditCatalogService::class)
        ->set('type', ServiceType::Hosting->value)
        ->set('name', 'Linux Hosting')
        ->set('price', '4.000')
        ->set('currency', Currency::Try->value)
        ->call('save')
        ->assertHasNoErrors();

    $hosting = CatalogService::query()->sole();

    // 2. The customer is added with the first contact and the card opens.
    Livewire::test(CreateCustomer::class)
        ->set('short_name', 'Logi')
        ->set('legal_title', 'Logitech Bilgisayar Ürünleri Ltd. Şti.')
        ->set('contact_name', 'Murat Kaya')
        ->set('contact_email', 'murat.kaya@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.customers.show', Customer::query()->sole()));

    $customer = Customer::query()->sole();

    // 3. The service is assigned from the card: suggested price and date, first period's charge.
    Livewire::test(AssignService::class, ['customer' => $customer->id])
        ->set('sold', "catalog_service:{$hosting->id}")
        ->assertSet('price', '4.000')
        ->assertSet('currency', Currency::Try->value)
        ->assertSet('start_date', '2026-03-01')
        ->set("items.{$hosting->id}.details.domain", 'logi.com.tr')
        ->call('save')
        ->assertHasNoErrors();

    $service = $customer->customerServices()->sole();
    $firstCharge = $customer->charges()->sole();

    expect($service->end_date->toDateString())->toBe('2027-03-01')
        ->and($service->items()->sole()->details)->toBe(['domain' => 'logi.com.tr'])
        ->and(mvpChargeState($firstCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Open, 'allocated' => 0, 'due_date' => '2026-03-01'])
        ->and(mvpTotalReceivable())->toBe('4.000 TL');

    // 4. 5.000 TL is paid from the top bar; the preview first shows the split.
    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('currency', 'TRY')
        ->set('amount', '5.000')
        ->set('show_allocations', true)
        ->assertSet("allocations.{$firstCharge->id}", '4.000')
        ->assertSee('4.000 TL borç kapatıldı, 1.000 TL alacak bakiyesine eklendi.')
        ->call('save')
        ->assertDispatched('notify', message: '4.000 TL borç kapatıldı, 1.000 TL alacak bakiyesine eklendi.');

    expect(mvpChargeState($firstCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Paid, 'allocated' => 400000, 'due_date' => '2026-03-01'])
        ->and($customer->creditBalances()['TRY']->amount)->toBe(100000)
        ->and(mvpTotalReceivable())->toBe('—');

    Livewire::test(Show::class, ['customer' => $customer])->assertSee('1.000 TL');

    // 5. 30 days before the end the renewal charge appears and the credit drops onto it.
    $this->travelTo('2027-01-30 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    $renewalCharge = $customer->charges()->whereKeyNot($firstCharge->id)->sole();

    expect(mvpChargeState($renewalCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Open, 'allocated' => 100000, 'due_date' => '2027-03-01'])
        ->and($customer->creditBalances())->toBe([])
        ->and(mvpTotalReceivable())->toBe('3.000 TL');

    expect(Livewire::test(ChargesIndex::class)->viewData('charges')->pluck('id')->all())->toContain($renewalCharge->id);

    // The payment window pays 500 TL short of the remaining 3.000 TL.
    Livewire::test(RecordPayment::class, ['customer' => $customer->id])
        ->set('amount', '2.500')
        ->call('save')
        ->assertDispatched('notify', message: '2.500 TL borç kapatıldı.');

    expect(mvpChargeState($renewalCharge)['allocated'])->toBe(350000)
        ->and($renewalCharge->fresh()->remainingAmount())->toBe(50000);

    // The period moves on at the end date; the unpaid 500 TL becomes overdue the day after.
    $this->travelTo('2027-03-01 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();
    $this->travelTo('2027-03-02 10:00:00');

    expect($service->refresh()->start_date->toDateString())->toBe('2027-03-01')
        ->and($service->end_date->toDateString())->toBe('2028-03-01')
        ->and($renewalCharge->fresh()->isOverdue())->toBeTrue()
        ->and(mvpTotalReceivable())->toBe('500 TL');

    Livewire::test(Today::class)->assertSee('Logi');

    // 6. 30 days before the next end the charge of the not yet started period is created.
    $this->travelTo('2028-01-31 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    $upcomingCharge = $customer->charges()->whereNotIn('id', [$firstCharge->id, $renewalCharge->id])->sole();

    expect(mvpChargeState($upcomingCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Open, 'allocated' => 0, 'due_date' => '2028-03-01']);

    // 7. The service is cancelled; the upcoming charge goes, the 500 TL past debt stays.
    $this->travelTo('2028-02-01 10:00:00');
    $paidBefore = [mvpChargeState($firstCharge), mvpChargeState($renewalCharge)];
    $allocationsBefore = PaymentAllocation::query()->orderBy('id')->get()->toArray();

    Livewire::test(CancelService::class, ['service' => $service->id])
        ->assertSet('cancel_upcoming_charges', true)
        ->assertSee(__('customer_services.cancel_upcoming_charges', ['amount' => '4.000 TL']))
        ->assertSee(__('customer_services.stays_overdue', ['amount' => '500 TL']))
        ->call('save')
        ->assertHasNoErrors();

    expect($service->refresh()->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($upcomingCharge->fresh()->status)->toBe(ChargeStatus::Cancelled)
        ->and($renewalCharge->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($renewalCharge->fresh()->remainingAmount())->toBe(50000)
        ->and($renewalCharge->fresh()->isOverdue())->toBeTrue()
        ->and(mvpChargeState($firstCharge))->toBe($paidBefore[0])
        ->and(mvpChargeState($renewalCharge))->toBe($paidBefore[1])
        ->and(PaymentAllocation::query()->orderBy('id')->get()->toArray())->toBe($allocationsBefore)
        ->and(mvpTotalReceivable())->toBe('500 TL');

    Livewire::test(Show::class, ['customer' => $customer])
        ->assertSee(__('customers.cancelled_services', ['count' => 1]));

    // Later scheduler runs create nothing for the cancelled service.
    foreach (['2028-03-01 09:00:00', '2029-01-31 09:00:00', '2029-03-01 09:00:00'] as $date) {
        $this->travelTo($date);
        $this->artisan('app:process-customer-services')->assertSuccessful();
    }

    expect($customer->charges()->count())->toBe(3)
        ->and($service->fresh()->status)->toBe(CustomerServiceStatus::Cancelled);
});
