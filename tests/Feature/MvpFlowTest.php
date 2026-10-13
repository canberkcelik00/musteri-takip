<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CancelledCustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Support\RecordActionsSection;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TodayTasks;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Text;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
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
 * The first number of "Bugün" (total receivable).
 */
function mvpTotalReceivable(): string
{
    $widget = new TodayStats;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    return (string) $stats[0]->getValue();
}

it('runs the main flow through the screens: customer, service, payment, renewal, cancellation', function () {
    $this->travelTo('2026-03-01 10:00:00');

    // 1. A catalog service is added from the catalog list.
    Livewire::test(ListCatalogServices::class)
        ->mountAction('create')
        ->fillForm([
            'type' => ServiceType::Hosting->value,
            'name' => 'Linux Hosting',
            'price' => '4.000',
            'currency' => Currency::Try->value,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $hosting = CatalogService::query()->sole();

    // 2. The customer is added with the first contact and the card opens.
    Livewire::test(ListCustomers::class)
        ->mountAction('createCustomer')
        ->fillForm([
            'short_name' => 'Logi',
            'legal_title' => 'Logitech Bilgisayar Ürünleri Ltd. Şti.',
            'contact' => ['name' => 'Murat Kaya', 'email' => 'murat.kaya@example.com'],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertRedirect(CustomerResource::getUrl('view', ['record' => Customer::query()->sole()]));

    $customer = Customer::query()->sole();

    // 3. The service is assigned from the card: suggested price and date, first period's charge.
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->mountAction(TestAction::make('assignService'))
        ->fillForm(['sold' => "catalog_service:{$hosting->id}"])
        ->assertSchemaStateSet(['price' => '4.000', 'currency' => Currency::Try, 'start_date' => '2026-03-01'])
        ->fillForm(["items.{$hosting->id}.details.domain" => 'logi.com.tr'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $service = $customer->customerServices()->sole();
    $firstCharge = $customer->charges()->sole();

    expect($service->end_date->toDateString())->toBe('2027-03-01')
        ->and($service->items()->sole()->details)->toBe(['domain' => 'logi.com.tr'])
        ->and(mvpChargeState($firstCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Open, 'allocated' => 0, 'due_date' => '2026-03-01'])
        ->and(mvpTotalReceivable())->toBe('4.000 TL');

    // 4. 5.000 TL is paid from the topbar; the preview first shows the split.
    $payment = topbarActions()
        ->mountAction('recordPayment')
        ->fillForm(['customer_id' => $customer->id, 'currency' => 'TRY', 'amount' => '5.000', 'show_allocations' => true])
        ->assertSchemaStateSet(["allocations.{$firstCharge->id}" => '4.000']);

    expect(mountedActionWindowText($payment))->toContain('4.000 TL borç kapatıldı, 1.000 TL alacak bakiyesine eklendi.');

    $payment
        ->callMountedAction()
        ->assertNotified('4.000 TL borç kapatıldı, 1.000 TL alacak bakiyesine eklendi.');

    expect(mvpChargeState($firstCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Paid, 'allocated' => 400000, 'due_date' => '2026-03-01'])
        ->and($customer->creditBalances()['TRY']->amount)->toBe(100000)
        ->and(mvpTotalReceivable())->toBe('—');

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])->assertSee('1.000 TL');

    // 5. 30 days before the end the renewal charge appears and the credit drops onto it.
    $this->travelTo('2027-01-30 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    $renewalCharge = $customer->charges()->whereKeyNot($firstCharge->id)->sole();

    expect(mvpChargeState($renewalCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Open, 'allocated' => 100000, 'due_date' => '2027-03-01'])
        ->and($customer->creditBalances())->toBe([])
        ->and(mvpTotalReceivable())->toBe('3.000 TL');

    Livewire::test(ListCharges::class)->assertCanSeeTableRecords([$renewalCharge]);

    // The row's "Ödeme al" pays 500 TL short of the remaining 3.000 TL.
    Livewire::test(ListCharges::class)
        ->callAction(TestAction::make('recordPayment')->table($renewalCharge), ['amount' => '2.500'])
        ->assertNotified('2.500 TL borç kapatıldı.');

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

    Livewire::test(TodayTasks::class)->assertSee('Logi');

    // 6. 30 days before the next end the charge of the not yet started period is created.
    $this->travelTo('2028-01-31 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    $upcomingCharge = $customer->charges()->whereNotIn('id', [$firstCharge->id, $renewalCharge->id])->sole();

    expect(mvpChargeState($upcomingCharge))->toBe(['amount' => 400000, 'status' => ChargeStatus::Open, 'allocated' => 0, 'due_date' => '2028-03-01']);

    // 7. The service is cancelled; the upcoming charge goes, the 500 TL past debt stays.
    $this->travelTo('2028-02-01 10:00:00');
    $paidBefore = [mvpChargeState($firstCharge), mvpChargeState($renewalCharge)];
    $allocationsBefore = PaymentAllocation::query()->orderBy('id')->get()->toArray();

    $cancel = [
        TestAction::make('editService')->table($service),
        TestAction::make('cancelService')->schemaComponent(RecordActionsSection::DANGER_ACTIONS_KEY),
    ];

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->mountAction($cancel)
        ->assertSchemaStateSet(['cancel_upcoming_charges' => true])
        ->assertFormFieldExists('cancel_upcoming_charges', checkFieldUsing: fn (Checkbox $field): bool => $field->getLabel() === 'Yaklaşan dönemin borcunu da iptal et (4.000 TL)')
        ->assertSchemaComponentExists('stays_overdue', checkComponentUsing: fn (Text $text): bool => $text->getContent() === '500 TL gecikmiş borç takipte kalacak.')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($service->refresh()->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($upcomingCharge->fresh()->status)->toBe(ChargeStatus::Cancelled)
        ->and($renewalCharge->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($renewalCharge->fresh()->remainingAmount())->toBe(50000)
        ->and($renewalCharge->fresh()->isOverdue())->toBeTrue()
        ->and(mvpChargeState($firstCharge))->toBe($paidBefore[0])
        ->and(mvpChargeState($renewalCharge))->toBe($paidBefore[1])
        ->and(PaymentAllocation::query()->orderBy('id')->get()->toArray())->toBe($allocationsBefore)
        ->and(mvpTotalReceivable())->toBe('500 TL');

    Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords([$service]);

    // Later scheduler runs create nothing for the cancelled service.
    foreach (['2028-03-01 09:00:00', '2029-01-31 09:00:00', '2029-03-01 09:00:00'] as $date) {
        $this->travelTo($date);
        $this->artisan('app:process-customer-services')->assertSuccessful();
    }

    expect($customer->charges()->count())->toBe(3)
        ->and($service->fresh()->status)->toBe(CustomerServiceStatus::Cancelled);
});
