<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CancelledCustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Support\RecordActionsSection;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Text;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * An active 4.000 TL service for 01.03.2026 – 01.03.2027; today is 15.06.2026, so the
 * charge of the period starting 01.03.2027 is the upcoming one.
 */
function cardService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

/**
 * Active services are rows of {@see CustomerServicesRelationManager}, cancelled ones of
 * {@see CancelledCustomerServicesRelationManager} (T-66) — the manager is picked from the
 * service's current status so every existing call site keeps working unchanged.
 */
function serviceRows(CustomerService $customerService)
{
    $manager = $customerService->status === CustomerServiceStatus::Cancelled
        ? CancelledCustomerServicesRelationManager::class
        : CustomerServicesRelationManager::class;

    return Livewire::test($manager, [
        'ownerRecord' => $customerService->customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

function rowAction(string $name, CustomerService $customerService): TestAction
{
    return TestAction::make($name)->table($customerService);
}

/**
 * A rarer service action, reached through the "Hizmet işlemleri" section of the edit panel.
 *
 * @return list<TestAction>
 */
function panelAction(string $name, CustomerService $customerService, bool $danger = false): array
{
    return [
        rowAction('editService', $customerService),
        TestAction::make($name)->schemaComponent($danger ? RecordActionsSection::DANGER_ACTIONS_KEY : RecordActionsSection::ACTIONS_KEY),
    ];
}

/**
 * Whether the edit panel of the service offers the action.
 */
function panelOffers(string $name, CustomerService $customerService, bool $danger = false): bool
{
    return actionOffered(serviceRows($customerService), panelAction($name, $customerService, $danger));
}

function chargeOfPeriod(CustomerService $customerService, string $periodStart, array $attributes = []): Charge
{
    $periodEnd = CarbonImmutable::parse($periodStart)->addYear()->toDateString();

    return Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => $periodStart,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        ...$attributes,
    ]);
}

describe('cancel window', function () {
    it('offers the upcoming period charge checked and cancels it with the date and note', function () {
        $customerService = cardService();
        $upcoming = chargeOfPeriod($customerService, '2027-03-01');

        serviceRows($customerService)
            ->mountAction(panelAction('cancelService', $customerService, danger: true))
            ->assertSchemaStateSet(['cancelled_on' => '2026-06-15', 'cancel_upcoming_charges' => true])
            ->assertFormFieldExists('cancel_upcoming_charges', checkFieldUsing: fn (Checkbox $field): bool => $field->getLabel() === 'Yaklaşan dönemin borcunu da iptal et (4.000 TL)')
            ->fillForm(['cancelled_on' => '2026-06-10', 'cancel_note' => 'Siteyi kapattı.'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $customerService->refresh();

        expect($customerService->status)->toBe(CustomerServiceStatus::Cancelled)
            ->and($customerService->cancelled_on->toDateString())->toBe('2026-06-10')
            ->and($customerService->cancel_note)->toBe('Siteyi kapattı.')
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Cancelled);
    });

    it('keeps the upcoming period charge when the box is unchecked', function () {
        $customerService = cardService();
        $upcoming = chargeOfPeriod($customerService, '2027-03-01');

        serviceRows($customerService)
            ->mountAction(panelAction('cancelService', $customerService, danger: true))
            ->fillForm(['cancel_upcoming_charges' => false])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Cancelled)
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('shows the overdue charge of a past period as staying and never cancels it', function () {
        $customerService = cardService();
        $overdue = chargeOfPeriod($customerService, '2026-03-01', ['amount' => 150000]);
        $upcoming = chargeOfPeriod($customerService, '2027-03-01');

        serviceRows($customerService)
            ->mountAction(panelAction('cancelService', $customerService, danger: true))
            ->assertSchemaComponentExists('stays_overdue', checkComponentUsing: fn (Text $text): bool => $text->getContent() === '1.500 TL gecikmiş borç takipte kalacak.')
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect($overdue->fresh()->status)->toBe(ChargeStatus::Open)
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Cancelled);
    });

    it('does not cancel an upcoming charge that has an allocation', function () {
        $customerService = cardService();
        $upcoming = chargeOfPeriod($customerService, '2027-03-01');

        app(RecordPayment::class)->handle($customerService->customer, [
            'amount' => 100000,
            'currency' => Currency::Try,
            'paid_on' => '2026-06-15',
            'payment_method_id' => PaymentMethod::query()->value('id'),
        ]);

        serviceRows($customerService)
            ->mountAction(panelAction('cancelService', $customerService, danger: true))
            ->assertFormFieldDoesNotExist('cancel_upcoming_charges')
            ->assertSchemaComponentExists('stays_open', checkComponentUsing: fn (Text $text): bool => $text->getContent() === '3.000 TL açık borç takipte kalacak.')
            ->callMountedAction();

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Cancelled)
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('offers no checkbox when there is no upcoming charge', function () {
        $customerService = cardService();

        serviceRows($customerService)
            ->mountAction(panelAction('cancelService', $customerService, danger: true))
            ->assertFormFieldDoesNotExist('cancel_upcoming_charges')
            ->callMountedAction();

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Cancelled);
    });
});

describe('edit', function () {
    it('opens filled with the current values and saves the changes', function () {
        $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();
        $customerService = cardService(['catalog_service_id' => $hosting->id, 'notes' => 'Eski not']);
        $item = $customerService->items()->sole();
        $item->update(['details' => ['server' => 'srv1']]);

        serviceRows($customerService)
            ->mountAction(rowAction('editService', $customerService))
            ->assertSchemaStateSet([
                'price' => '4.000',
                'currency' => Currency::Try,
                'start_date' => '2026-03-01',
                'end_date' => '2027-03-01',
                'notes' => 'Eski not',
                "items.{$item->id}.details.server" => 'srv1',
            ])
            ->fillForm([
                'price' => '4.500,50',
                'currency' => Currency::Usd->value,
                'start_date' => '2026-03-05',
                'notes' => 'Yeni not',
                "items.{$item->id}.details.server" => 'srv2',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $customerService->refresh();

        expect($customerService->price)->toBe(450050)
            ->and($customerService->currency)->toBe(Currency::Usd)
            ->and($customerService->start_date->toDateString())->toBe('2026-03-05')
            ->and($customerService->notes)->toBe('Yeni not')
            ->and($item->fresh()->details)->toBe(['server' => 'srv2']);
    });

    it('warns when the end date cannot change and saves nothing', function () {
        $customerService = cardService();
        chargeOfPeriod($customerService, '2027-03-01');

        serviceRows($customerService)
            ->mountAction(rowAction('editService', $customerService))
            ->fillForm(['price' => '5.000', 'end_date' => '2027-03-15'])
            ->callMountedAction()
            ->assertHasFormErrors(['end_date']);

        expect($customerService->fresh()->price)->toBe(400000)
            ->and($customerService->fresh()->end_date->toDateString())->toBe('2027-03-01');
    });

    it('locks price, currency and dates of a cancelled service but not its notes', function () {
        $customerService = cardService(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        serviceRows($customerService)
            ->mountAction(rowAction('editService', $customerService))
            ->assertFormFieldDisabled('price')
            ->assertFormFieldDisabled('currency')
            ->assertFormFieldDisabled('start_date')
            ->assertFormFieldDisabled('end_date')
            ->assertFormFieldEnabled('notes')
            ->fillForm(['notes' => 'Arşiv notu'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect($customerService->fresh()->notes)->toBe('Arşiv notu')
            ->and($customerService->fresh()->price)->toBe(400000);
    });
});

describe('free flag', function () {
    it('marks the service free and cancels the upcoming charge by default', function () {
        $customerService = cardService();
        $upcoming = chargeOfPeriod($customerService, '2027-03-01');

        serviceRows($customerService)
            ->mountAction(panelAction('markFree', $customerService))
            ->assertSchemaStateSet(['cancel_upcoming_charges' => true])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect($customerService->fresh()->is_free)->toBeTrue()
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Cancelled);
    });

    it('marks the service free and keeps the upcoming charge when the box is unchecked', function () {
        $customerService = cardService();
        $upcoming = chargeOfPeriod($customerService, '2027-03-01');

        serviceRows($customerService)
            ->mountAction(panelAction('markFree', $customerService))
            ->fillForm(['cancel_upcoming_charges' => false])
            ->callMountedAction();

        expect($customerService->fresh()->is_free)->toBeTrue()
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('makes a free service paid and creates the upcoming charge inside the renewal window', function () {
        $customerService = cardService(['is_free' => true]);
        $this->travelTo('2027-02-10 10:00:00');

        serviceRows($customerService)
            ->mountAction(panelAction('removeFree', $customerService))
            ->assertSchemaStateSet(['price' => '4.000'])
            ->fillForm(['price' => '4.500'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Hizmet ücretli yapıldı; 4.500 TL tutarında borç oluştu.');

        $charge = $customerService->charges()->sole();

        expect($customerService->fresh()->is_free)->toBeFalse()
            ->and($customerService->fresh()->price)->toBe(450000)
            ->and($charge->amount)->toBe(450000)
            ->and($charge->period_start->toDateString())->toBe('2027-03-01');
    });

    it('makes a free service paid without a charge before the renewal window', function () {
        $customerService = cardService(['is_free' => true]);

        serviceRows($customerService)
            ->mountAction(panelAction('removeFree', $customerService))
            ->callMountedAction()
            ->assertNotified('Hizmet ücretli yapıldı.');

        expect($customerService->fresh()->is_free)->toBeFalse()
            ->and($customerService->charges()->count())->toBe(0);
    });

    it('rejects a price of zero when making the service paid', function () {
        $customerService = cardService(['is_free' => true, 'price' => 0]);

        serviceRows($customerService)
            ->mountAction(panelAction('removeFree', $customerService))
            ->callMountedAction()
            ->assertHasFormErrors(['price']);

        expect($customerService->fresh()->is_free)->toBeTrue();
    });
});

describe('reactivate', function () {
    it('reopens the cancelled upcoming charge while the period has not ended', function () {
        $customerService = cardService();
        $upcoming = chargeOfPeriod($customerService, '2027-03-01', [
            'status' => ChargeStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Hizmet iptal edildi.',
        ]);
        $customerService->update(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        serviceRows($customerService)
            ->mountAction(rowAction('reactivate', $customerService))
            ->assertFormFieldDoesNotExist('start_date')
            ->assertSchemaStateSet(['reopen_upcoming_charge' => true])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Active)
            ->and($customerService->fresh()->end_date->toDateString())->toBe('2027-03-01')
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('offers no reopen box when no cancelled charge exists', function () {
        $customerService = cardService(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        serviceRows($customerService)
            ->mountAction(rowAction('reactivate', $customerService))
            ->assertFormFieldDoesNotExist('reopen_upcoming_charge')
            ->callMountedAction();

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Active);
    });

    it('asks for a new start date once the period has ended and creates the first charge', function () {
        $customerService = cardService([
            'start_date' => '2025-03-01',
            'end_date' => '2026-03-01',
            'status' => CustomerServiceStatus::Cancelled,
            'cancelled_on' => '2025-12-01',
        ]);

        serviceRows($customerService)
            ->mountAction(rowAction('reactivate', $customerService))
            ->assertSchemaStateSet(['start_date' => '2026-06-15', 'create_first_charge' => 1])
            ->fillForm(['start_date' => null])
            ->callMountedAction()
            ->assertHasFormErrors(['start_date' => 'required'])
            ->fillForm(['start_date' => '2026-06-15'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $customerService->refresh();
        $charge = $customerService->charges()->sole();

        expect($customerService->status)->toBe(CustomerServiceStatus::Active)
            ->and($customerService->start_date->toDateString())->toBe('2026-06-15')
            ->and($customerService->end_date->toDateString())->toBe('2027-06-15')
            ->and($charge->amount)->toBe(400000)
            ->and($charge->period_start->toDateString())->toBe('2026-06-15');
    });

    it('skips the first charge when "Oluşturma" is chosen', function () {
        $customerService = cardService([
            'start_date' => '2025-03-01',
            'end_date' => '2026-03-01',
            'status' => CustomerServiceStatus::Cancelled,
            'cancelled_on' => '2025-12-01',
        ]);

        serviceRows($customerService)
            ->mountAction(rowAction('reactivate', $customerService))
            ->fillForm(['start_date' => '2026-06-15', 'create_first_charge' => 0])
            ->callMountedAction();

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Active)
            ->and($customerService->charges()->count())->toBe(0);
    });

    it('warns for an archived customer and changes nothing', function () {
        $customer = Customer::factory()->archived()->create();
        $customerService = cardService([
            'customer_id' => $customer->id,
            'status' => CustomerServiceStatus::Cancelled,
            'cancelled_on' => '2026-06-01',
        ]);

        serviceRows($customerService)
            ->mountAction(rowAction('reactivate', $customerService))
            ->assertSchemaComponentExists('customer_archived')
            ->callMountedAction()
            ->assertNotified('Önce müşteriyi arşivden çıkarın.');

        expect($customerService->fresh()->status)->toBe(CustomerServiceStatus::Cancelled);
    });
});

describe('row actions by state', function () {
    it('offers mark free and cancel in the panel of an active paid service; the row keeps only edit', function () {
        $customerService = cardService();

        serviceRows($customerService)
            ->assertActionVisible(rowAction('editService', $customerService))
            ->assertActionHidden(rowAction('reactivate', $customerService));

        expect(panelOffers('markFree', $customerService))->toBeTrue()
            ->and(panelOffers('cancelService', $customerService, danger: true))->toBeTrue()
            ->and(panelOffers('removeFree', $customerService))->toBeFalse();
    });

    it('hides make paid on the card for a cancelled free service', function () {
        $customerService = cardService(['is_free' => true, 'status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        expect(panelOffers('removeFree', $customerService))->toBeFalse();
    });

    it('shows make paid instead of mark free for a free service', function () {
        $customerService = cardService(['is_free' => true]);

        expect(panelOffers('removeFree', $customerService))->toBeTrue()
            ->and(panelOffers('markFree', $customerService))->toBeFalse()
            ->and(panelOffers('cancelService', $customerService, danger: true))->toBeTrue();
    });

    it('keeps edit and reactivate on the row of a cancelled service and offers no cancel or mark free', function () {
        $customerService = cardService(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        serviceRows($customerService)
            ->assertActionVisible(rowAction('editService', $customerService))
            ->assertActionVisible(rowAction('reactivate', $customerService));

        expect(panelOffers('reactivate', $customerService))->toBeTrue()
            ->and(panelOffers('cancelService', $customerService, danger: true))->toBeFalse()
            ->and(panelOffers('markFree', $customerService))->toBeFalse()
            ->and(panelOffers('removeFree', $customerService))->toBeFalse();
    });
});

describe('delete', function () {
    it('deletes a service that has no charge from the card', function () {
        $customerService = cardService();

        serviceRows($customerService)
            ->assertActionVisible(panelAction('delete', $customerService, danger: true))
            ->callAction(panelAction('delete', $customerService, danger: true));

        expect(CustomerService::query()->find($customerService->id))->toBeNull();
    });

    it('offers delete for a cancelled service without a charge too', function () {
        $customerService = cardService(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        expect(panelOffers('delete', $customerService, danger: true))->toBeTrue();
    });

    it('hides delete once the service has a charge, cancelled ones included', function (bool $cancelled) {
        $customerService = cardService();
        chargeOfPeriod($customerService, '2026-03-01', $cancelled ? ['status' => ChargeStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Yanlış dönem'] : []);

        expect(panelOffers('delete', $customerService, danger: true))->toBeFalse()
            ->and(panelOffers('cancelService', $customerService, danger: true))->toBeTrue();
    })->with(['open charge' => false, 'cancelled charge' => true]);
});
