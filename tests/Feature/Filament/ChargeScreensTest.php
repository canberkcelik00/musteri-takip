<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Support\RecordActionsSection;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
});

function chargesPage()
{
    return Livewire::test(ListCharges::class);
}

function chargesOnCard(Customer $customer)
{
    return Livewire::test(ChargesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

function chargeRowAction(string $name, Charge $charge): TestAction
{
    return TestAction::make($name)->table($charge);
}

/**
 * A rare charge action, reached through the "Borç işlemleri" section of the edit panel.
 *
 * @return list<TestAction>
 */
function chargePanelAction(string $name, Charge $charge, string $section = RecordActionsSection::ACTIONS_KEY): array
{
    return [
        chargeRowAction('editCharge', $charge),
        TestAction::make($name)->schemaComponent($section),
    ];
}

/**
 * Pay part of a charge so that it has an allocation.
 */
function payPartOf(Charge $charge, int $amount): void
{
    app(RecordPayment::class)->handle($charge->customer, [
        'amount' => $amount,
        'currency' => $charge->currency,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ], [$charge->id => $amount]);
}

it('lists the open charges of all customers, oldest due date first', function () {
    $later = Charge::factory()->create(['due_date' => '2026-07-01']);
    $earlier = Charge::factory()->create(['due_date' => '2026-05-01']);
    $paid = Charge::factory()->paid()->create();
    $cancelled = Charge::factory()->cancelled()->create();

    chargesPage()
        ->assertOk()
        ->assertCanSeeTableRecords([$earlier, $later], inOrder: true)
        ->assertCanNotSeeTableRecords([$paid, $cancelled]);
});

it('marks an open charge due within 7 days as due soon, in orange, and a farther one as plain open', function () {
    $dueSoon = Charge::factory()->create(['due_date' => '2026-06-22']);
    $open = Charge::factory()->create(['due_date' => '2026-07-10']);

    $html = chargesPage()->html();

    expect($html)->toContain('Vadesi yaklaşıyor')
        ->and($html)->toContain('fi-color-warning');

    // G-4B.4 (T-80): from `lg` up the state is a "Durum" column of its own again, as in the
    // reference screen; the "Tutar" column is gone. Only a colored state is a badge: a plain
    // open charge shows its state as plain text.
    $table = chargesPage()->instance()->getTable();
    $status = $table->getColumn('status');

    expect($table->getColumn('amount'))->toBeNull()
        ->and($status->record($dueSoon)->getState())->toBe('Vadesi yaklaşıyor')
        ->and($status->isBadge())->toBeTrue()
        ->and($status->getColor('Vadesi yaklaşıyor'))->toBe('warning')
        ->and($status->record($open)->getState())->toBe('Açık')
        ->and($status->record($open)->isBadge())->toBeFalse();

    // Below `lg` the "Durum" column is hidden: the badge moves under "Kalan" (a plain open
    // charge has none).
    $remaining = $table->getColumn('remaining');

    // The column and its header appear from `lg` (1024 px), not `md`: at 768 px the extra column
    // made the list wider than its card (T-80).
    $html = chargesPage()->html();

    expect($html)->toMatch('/fi-ta-header-cell-status[^"]*lg:fi-visible/')
        ->and($html)->not->toMatch('/fi-ta-header-cell-status[^"]*md:fi-visible/');

    expect($remaining->record($dueSoon)->getDescriptionBelow()->toHtml())->toContain('fi-badge-below-lg')->toContain('Vadesi yaklaşıyor')->toContain('fi-color-warning')
        ->and($remaining->record($open)->getDescriptionBelow())->toBeNull();
});

it('filters the list to overdue charges only', function () {
    $overdue = Charge::factory()->create(['due_date' => '2026-06-14']);
    $dueToday = Charge::factory()->create(['due_date' => '2026-06-15']);
    $upcoming = Charge::factory()->create(['due_date' => '2026-07-01']);

    chargesPage()
        ->filterTable('only_overdue', true)
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$dueToday, $upcoming]);
});

it('hides the charges of archived customers only with the "hide archived" filter, alone or with another filter', function (string $filter, string $dueDate) {
    $kept = Charge::factory()->create(['due_date' => $dueDate]);
    $archived = Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => $dueDate]);

    chargesPage()
        ->assertCanSeeTableRecords([$kept, $archived])
        ->filterTable($filter, true)
        ->assertCanSeeTableRecords([$kept, $archived])
        ->filterTable('hide_archived', true)
        ->assertCanSeeTableRecords([$kept])
        ->assertCanNotSeeTableRecords([$archived])
        ->removeTableFilter($filter)
        ->assertCanNotSeeTableRecords([$archived]);
})->with([
    'only overdue' => ['only_overdue', '2026-06-14'],
    'due this month' => ['due_this_month', '2026-06-25'],
    'due soon' => ['due_soon', '2026-06-18'],
]);

it('does not count a fully allocated charge as overdue in the filter', function () {
    $customer = Customer::factory()->create();
    $settled = Charge::factory()->for($customer)->create(['amount' => 10000, 'due_date' => '2026-06-01']);
    $partial = Charge::factory()->for($customer)->create(['amount' => 10000, 'due_date' => '2026-06-02']);
    payPartOf($settled, 10000);
    payPartOf($partial, 4000);

    chargesPage()
        ->filterTable('only_overdue', true)
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$settled]);
});

it('filters the list by currency', function () {
    $lira = Charge::factory()->create(['currency' => Currency::Try]);
    $dollar = Charge::factory()->create(['currency' => Currency::Usd]);

    chargesPage()
        ->filterTable('currency', 'USD')
        ->assertCanSeeTableRecords([$dollar])
        ->assertCanNotSeeTableRecords([$lira]);
});

it('locks the amount field of a charge with allocations and lets the due date change', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-20']);
    payPartOf($charge, 30000);

    chargesPage()
        ->mountAction(chargeRowAction('editCharge', $charge))
        ->assertSchemaComponentExists('amount', checkComponentUsing: fn (MoneyInput $field): bool => $field->isDisabled())
        ->fillForm(['due_date' => '2026-07-10'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $charge->refresh();

    expect($charge->amount)->toBe(100000)
        ->and($charge->due_date->toDateString())->toBe('2026-07-10');
});

it('leaves the amount field of a charge without allocations editable and changes the amount', function () {
    $charge = Charge::factory()->create(['amount' => 100000, 'due_date' => '2026-06-20']);

    chargesPage()
        ->mountAction(chargeRowAction('editCharge', $charge))
        ->assertSchemaComponentExists('amount', checkComponentUsing: fn (MoneyInput $field): bool => ! $field->isDisabled())
        ->fillForm(['amount' => '1.250,50', 'due_date' => '2026-07-01'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $charge->refresh();

    expect($charge->amount)->toBe(125050)
        ->and($charge->due_date->toDateString())->toBe('2026-07-01');
});

it('adds a manual charge for a chosen customer from the topbar', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    topbarActions()
        ->mountAction('createManualCharge')
        ->assertSchemaComponentVisible('customer_id')
        ->fillForm([
            'customer_id' => $customer->id,
            'description' => 'Ek destek saati',
            'amount' => '750',
            'currency' => 'TRY',
            'due_date' => '2026-06-30',
            'customer_service_id' => $service->id,
        ])
        ->callMountedAction()
        ->assertNotified('Borç eklendi.');

    // The action already unmounted after the successful call (no default form left to check),
    // so success is asserted through the notification and the created record instead of
    // assertHasNoFormErrors().
    $charge = $customer->charges()->sole();

    expect($charge->description)->toBe('Ek destek saati')
        ->and($charge->amount)->toBe(75000)
        ->and($charge->customer_service_id)->toBe($service->id)
        ->and($charge->period_start)->toBeNull();
});

it('fixes the customer on the card and applies the credit balance to the new manual charge', function () {
    $customer = Customer::factory()->create();
    app(RecordPayment::class)->handle($customer, [
        'amount' => 20000,
        'currency' => Currency::Try,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ]);

    chargesOnCard($customer)
        ->mountAction(TestAction::make('createManualCharge')->table())
        ->assertSchemaComponentHidden('customer_id')
        ->fillForm([
            'description' => 'Kurulum',
            'amount' => '500',
            'currency' => 'TRY',
            'due_date' => '2026-06-30',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $charge = $customer->charges()->sole();

    expect($charge->allocatedAmount())->toBe(20000)
        ->and($charge->remainingAmount())->toBe(30000);
});

it('requires a description for a manual charge', function () {
    $customer = Customer::factory()->create();

    chargesOnCard($customer)
        ->mountAction(TestAction::make('createManualCharge')->table())
        ->fillForm(['description' => '', 'amount' => '500', 'currency' => 'TRY', 'due_date' => '2026-06-30'])
        ->callMountedAction()
        ->assertHasFormErrors(['description' => 'required']);

    expect($customer->charges()->count())->toBe(0);
});

it('splits a charge and keeps the total', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-20', 'description' => 'Hosting']);

    $card = chargesOnCard($customer)
        ->mountAction(chargePanelAction('splitCharge', $charge))
        ->fillForm(['amount' => '400', 'due_date' => '2026-09-01'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Borç bölündü.');

    // cancelParentActions(): the edit panel closes together with the nested form modal.
    expect($card->get('mountedActions'))->toBe([]);

    $piece = $customer->charges()->whereKeyNot($charge->id)->sole();

    expect($charge->refresh()->amount)->toBe(60000)
        ->and($piece->amount)->toBe(40000)
        ->and($piece->due_date->toDateString())->toBe('2026-09-01')
        ->and($piece->description)->toBe('Hosting');
});

it('refuses to split off the whole amount and leaves the charge unchanged', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000]);

    chargesOnCard($customer)
        ->mountAction(chargePanelAction('splitCharge', $charge))
        ->fillForm(['amount' => '1.000', 'due_date' => '2026-09-01'])
        ->callMountedAction()
        ->assertHasFormErrors(['amount']);

    expect($charge->refresh()->amount)->toBe(100000)
        ->and($customer->charges()->count())->toBe(1);
});

it('cancels a charge only with a reason', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create();

    chargesOnCard($customer)
        ->mountAction(chargePanelAction('cancelCharge', $charge, RecordActionsSection::DANGER_ACTIONS_KEY))
        ->fillForm(['reason' => ''])
        ->callMountedAction()
        ->assertHasFormErrors(['reason' => 'required']);

    expect($charge->refresh()->status)->toBe(ChargeStatus::Open);

    $card = chargesOnCard($customer)
        ->mountAction(chargePanelAction('cancelCharge', $charge, RecordActionsSection::DANGER_ACTIONS_KEY))
        ->fillForm(['reason' => 'Yanlış girildi'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Borç iptal edildi.');

    expect($card->get('mountedActions'))->toBe([]);

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Cancelled)
        ->and($charge->cancel_reason)->toBe('Yanlış girildi');
});

it('hides split and cancel for a charge with allocations but keeps edit', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000]);
    payPartOf($charge, 30000);

    chargesPage()
        ->assertTableActionVisible('editCharge', $charge)
        ->mountAction(chargeRowAction('editCharge', $charge))
        ->assertSchemaComponentHidden(RecordActionsSection::ACTIONS_KEY, 'mountedActionSchema0');

    chargesOnCard($customer)
        ->assertTableActionVisible('editCharge', $charge)
        ->mountAction(chargeRowAction('editCharge', $charge))
        ->assertSchemaComponentHidden(RecordActionsSection::ACTIONS_KEY, 'mountedActionSchema0');
});

it('hides every charge action for paid and cancelled charges', function () {
    $customer = Customer::factory()->create();
    $paid = Charge::factory()->for($customer)->paid()->create();
    $cancelled = Charge::factory()->for($customer)->cancelled()->create();

    chargesPage()
        ->removeTableFilter('status')
        ->assertTableActionHidden('editCharge', $paid)
        ->assertTableActionHidden('recordPayment', $paid)
        ->assertTableActionHidden('editCharge', $cancelled)
        ->assertTableActionHidden('recordPayment', $cancelled);
});

it('refreshes the card and the list when a charge changes', function () {
    $customer = Customer::factory()->create();

    chargesOnCard($customer)
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk();

    Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk();

    chargesPage()
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk();
});

it('adds a manual charge from the topbar and shows it wherever the customer\'s debt is listed', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    topbarActions()->callAction('createManualCharge', [
        'customer_id' => $customer->id,
        'description' => 'Ek destek saati',
        'amount' => '750',
        'currency' => 'TRY',
        'due_date' => '2026-06-30',
    ]);
    $charge = $customer->charges()->sole();

    Livewire::test(ListCustomers::class)
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk()
        ->assertSee('Logi');

    chargesPage()
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk()
        ->assertCanSeeTableRecords([$charge]);

    chargesOnCard($customer)
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk()
        ->assertCanSeeTableRecords([$charge]);
});

/**
 * The text of the list's total row (empty when the list has none).
 */
function chargesTotalRow(string $html): string
{
    preg_match('#<tfoot>.*?</tfoot>#s', $html, $match);

    return $match[0] ?? '';
}

it('totals the remaining open debt per currency under "Kalan", minus partial payments, without paid or cancelled charges', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 10000, 'currency' => Currency::Try, 'due_date' => '2026-07-10']);
    $partial = Charge::factory()->for($customer)->create(['amount' => 20000, 'currency' => Currency::Try, 'due_date' => '2026-07-11']);
    Charge::factory()->for($customer)->create(['amount' => 7000, 'currency' => Currency::Usd, 'due_date' => '2026-07-12']);
    Charge::factory()->for($customer)->paid()->create(['amount' => 99900, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->cancelled()->create(['amount' => 88800, 'currency' => Currency::Usd]);
    payPartOf($partial, 5000);

    $total = chargesTotalRow(chargesPage()->html());

    // The total row's label sits in whichever cell ends right before "Kalan" (G-4B.7): the single
    // cell below `md`, the three-column cell from `md`, the "Durum"-aligned cell from `lg`.
    expect($total)->toContain('fi-cell-below-md')
        ->toContain('fi-cell-from-md')
        ->toContain('fi-cell-from-lg')
        ->toContain('fi-hidden-from-lg');

    // 100 TL + (200 TL - 50 TL) = 250 TL and 70 USD, each currency on its own line.
    expect($total)->toContain(__('charges.total'))
        ->toContain((new Money(25000, Currency::Try))->format())
        ->toContain((new Money(7000, Currency::Usd))->format())
        ->not->toContain((new Money(99900, Currency::Try))->format())
        ->not->toContain((new Money(88800, Currency::Usd))->format());
});

it('follows the filters in the total row, never adds different currencies and has none when no open charge is listed', function () {
    Charge::factory()->create(['amount' => 10000, 'currency' => Currency::Try, 'due_date' => '2026-07-10']);
    Charge::factory()->create(['amount' => 7000, 'currency' => Currency::Usd, 'due_date' => '2026-07-12']);
    Charge::factory()->paid()->create();

    $usdOnly = chargesTotalRow(chargesPage()->filterTable('currency', 'USD')->html());

    expect($usdOnly)->toContain((new Money(7000, Currency::Usd))->format())
        ->not->toContain((new Money(10000, Currency::Try))->format());

    expect(chargesPage()->filterTable('status', ChargeStatus::Paid->value)->html())->not->toContain('<tfoot');
});

it('writes the original amount under a partly paid charge with the right Turkish suffix, and only there', function () {
    $lira = Charge::factory()->create(['amount' => 20000, 'currency' => Currency::Try]);
    $euro = Charge::factory()->create(['amount' => 30000, 'currency' => Currency::Eur]);
    Charge::factory()->create(['amount' => 40000, 'currency' => Currency::Try]);
    payPartOf($lira, 5000);
    payPartOf($euro, 5000);

    chargesPage()
        ->assertSee((new Money(20000, Currency::Try))->format()."'den")
        ->assertSee((new Money(30000, Currency::Eur))->format()."'dan")
        ->assertDontSee((new Money(40000, Currency::Try))->format()."'den");
});

// --- Dar ekran düzeni (G-4B.7): 375 px'e kadar yatay kayma yok ---------------

it('wraps the customer and the description of the charge list, which shows "Açıklama" and "Vade" from md', function () {
    $table = chargesPage()->instance()->getTable();

    expect($table->getColumn('customer.short_name')->canWrap())->toBeTrue()
        ->and($table->getColumn('customer.short_name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('description')->canWrap())->toBeTrue()
        ->and($table->getColumn('description')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('due_date')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('status')->getVisibleFrom())->toBe('lg')
        ->and($table->getColumn('status')->getExtraAttributes())->toBe(['class' => 'fi-badge-wrap'])
        ->and($table->getColumn('remaining')->getVisibleFrom())->toBeNull();
});

it('puts the description and the due date under the customer name for narrow screens, escaped', function () {
    $charge = Charge::factory()->create(['description' => '<b>Hosting & SSL</b>', 'due_date' => '2026-03-05']);

    $html = chargesPage()->instance()->getTable()
        ->getColumn('customer.short_name')->record($charge)->getDescriptionBelow()->toHtml();

    expect($html)->toContain('fi-line-below-md')
        ->toContain('&lt;b&gt;Hosting &amp; SSL&lt;/b&gt;')
        ->not->toContain('<b>')
        ->toContain(__('charges.due_date').': 05.03.2026');
});

it('hides "Düzenle" below md and keeps "Ödeme al" in the row at every width', function () {
    $table = chargesPage()->instance()->getTable();

    expect($table->getAction('editCharge')->getExtraAttributes())->toBe(['class' => 'fi-hidden-below-md'])
        ->and($table->getAction('recordPayment')->getExtraAttributes())->toBe([]);
});

it('still finds a charge by its description although the column is hidden on narrow screens', function () {
    $match = Charge::factory()->create(['description' => 'Kurumsal e-posta yenileme']);
    $other = Charge::factory()->create(['description' => 'Alan adı yenileme']);

    chargesPage()
        ->searchTable('Kurumsal')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});
