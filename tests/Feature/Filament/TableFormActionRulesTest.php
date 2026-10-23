<?php

/*
 * G-4.17: the table, form and action rules of docs/UI.md §2 (Kural 4, 6, 7, 8, 9) and §5 hold in
 * every list and panel of the app, not just in the screen a rule was first built for.
 */

use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeErrorReason;
use App\Enums\CustomerServiceErrorReason;
use App\Enums\CustomerServiceStatus;
use App\Enums\QuoteErrorReason;
use App\Exceptions\InvalidCharge;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use App\Filament\Actions\AssignCustomerServiceAction;
use App\Filament\Actions\CreateCustomerAction;
use App\Filament\Actions\EditCustomerAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CancelledCustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\QuotesRelationManager;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Support\FieldError;
use App\Filament\Support\RecordActionsSection;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Livewire\TopbarActions;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * @param  class-string  $class
 * @param  array<string, mixed>  $parameters
 */
function rulesTable(string $class, array $parameters = []): Table
{
    return Livewire::test($class, $parameters)->instance()->getTable();
}

/**
 * A form opens in the centered form window (T-84): the shared width class, sticky header and footer, no slide-over.
 */
function expectFormWindow(Action $action): void
{
    expect($action->isModalSlideOver())->toBeFalse("{$action->getName()} opens as a slide-over")
        ->and($action->getModalWidth())->toBe(AppServiceProvider::FORM_WINDOW_CLASS, "{$action->getName()} has the form window width")
        ->and($action->isModalHeaderSticky())->toBeTrue()
        ->and($action->isModalFooterSticky())->toBeTrue();
}

/**
 * The row actions a record shows, flattened, in the state the record is in.
 *
 * @return list<Action>
 */
function visibleRowActions(Table $table, Model $record): array
{
    return collect($table->getFlatRecordActions())
        ->filter(fn (Action $action): bool => $action->record($record)->isVisible())
        ->values()
        ->all();
}

/**
 * @return array<string, array{0: class-string, 1: array<string, mixed>}>
 */
function everyTable(): array
{
    $customer = Customer::factory()->create();
    $card = ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class];

    return [
        'Müşteriler' => [ListCustomers::class, []],
        'Açık borçlar' => [ListCharges::class, []],
        'Ödemeler' => [ListPayments::class, []],
        'Katalog hizmetleri' => [ListCatalogServices::class, []],
        'Paketler' => [ListPackages::class, []],
        'Teklifler' => [ListQuotes::class, []],
        'Ödeme yöntemleri' => [ManagePaymentMethods::class, []],
        'Kart: hizmetler' => [CustomerServicesRelationManager::class, $card],
        'Kart: iptal edilenler' => [CancelledCustomerServicesRelationManager::class, $card],
        'Kart: açık borçlar' => [ChargesRelationManager::class, $card],
        'Kart: son ödemeler' => [PaymentsRelationManager::class, $card],
        'Kart: kişiler' => [ContactsRelationManager::class, $card],
        'Kart: teklifler' => [QuotesRelationManager::class, $card],
    ];
}

function payPartOfCharge(Charge $charge, int $amount): void
{
    app(RecordPayment::class)->handle($charge->customer, [
        'amount' => $amount,
        'currency' => $charge->currency,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ], [$charge->id => $amount]);
}

describe('rows', function () {
    it('has no "⋮" menu in any list', function () {
        foreach (everyTable() as $label => [$class, $parameters]) {
            $groups = collect(rulesTable($class, $parameters)->getRecordActions())
                ->filter(fn (mixed $action): bool => $action instanceof ActionGroup);

            expect($groups)->toHaveCount(0, "{$label} still has an action group");
        }
    });

    it('keeps at most two labelled actions on a row, whatever state the record is in', function () {
        $customer = Customer::factory()->create();
        $records = [
            [ListCustomers::class, [], [$customer, Customer::factory()->archived()->create()]],
            [ListCharges::class, [], [
                Charge::factory()->create(),
                tap(Charge::factory()->create(['amount' => 100000]), fn (Charge $charge) => payPartOfCharge($charge, 30000)),
                Charge::factory()->paid()->create(),
                Charge::factory()->cancelled()->create(),
            ]],
            [ListPayments::class, [], [Payment::factory()->create()]],
            [ListCatalogServices::class, [], [CatalogService::factory()->create(), CatalogService::factory()->archived()->create()]],
            [ListPackages::class, [], [Package::factory()->withCatalogServices()->create(), Package::factory()->withCatalogServices()->archived()->create()]],
            [ManagePaymentMethods::class, [], PaymentMethod::query()->get()->all()],
            [ListQuotes::class, [], [
                Quote::factory()->create(),
                Quote::factory()->sent()->create(),
                Quote::factory()->expired()->create(),
                Quote::factory()->accepted()->create(),
                Quote::factory()->rejected()->create(),
            ]],
        ];

        foreach ($records as [$class, $parameters, $models]) {
            $table = rulesTable($class, $parameters);

            foreach ($models as $model) {
                $actions = visibleRowActions($table, $model);

                expect(count($actions))->toBeLessThanOrEqual(2, "{$class} shows too many row actions")
                    ->and(collect($actions)->every(fn (Action $action): bool => filled($action->getLabel())))->toBeTrue("{$class} has an unlabelled row action");
            }
        }

        $card = ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class];
        $active = CustomerService::factory()->for($customer)->create();
        $free = CustomerService::factory()->for($customer)->free()->create();
        $cancelled = CustomerService::factory()->for($customer)->cancelled()->create();
        $charge = Charge::factory()->for($customer)->create();
        $payment = Payment::factory()->for($customer)->create();

        foreach ([$active, $free] as $service) {
            expect(count(visibleRowActions(rulesTable(CustomerServicesRelationManager::class, $card), $service)))->toBeLessThanOrEqual(2);
        }

        expect(count(visibleRowActions(rulesTable(CancelledCustomerServicesRelationManager::class, $card), $cancelled)))->toBeLessThanOrEqual(2)
            ->and(count(visibleRowActions(rulesTable(ChargesRelationManager::class, $card), $charge)))->toBeLessThanOrEqual(2)
            ->and(count(visibleRowActions(rulesTable(PaymentsRelationManager::class, $card), $payment)))->toBeLessThanOrEqual(2)
            ->and(rulesTable(ContactsRelationManager::class, $card)->getFlatRecordActions())->toHaveCount(0);
    });

    it('offers "Ödeme al" and "Düzenle" on an open charge row of the list, and neither on a paid or cancelled one', function () {
        $open = Charge::factory()->create();
        $paid = Charge::factory()->paid()->create();
        $cancelled = Charge::factory()->cancelled()->create();

        $table = rulesTable(ListCharges::class);

        expect(collect(visibleRowActions($table, $open))->map->getName()->all())->toBe(['recordPayment', 'editCharge'])
            ->and(collect(visibleRowActions($table, $paid))->map->getName()->all())->toBe([])
            ->and(collect(visibleRowActions($table, $cancelled))->map->getName()->all())->toBe([]);
    });

    it('offers only "Ödeme al" on an open charge row of the card, whose description opens "Düzenle" (G-4B.5)', function () {
        $open = Charge::factory()->create();
        $paid = Charge::factory()->paid()->create();

        $table = rulesTable(ChargesRelationManager::class, ['ownerRecord' => $open->customer, 'pageClass' => ViewCustomer::class]);

        expect(collect(visibleRowActions($table, $open))->map->getName()->all())->toBe(['recordPayment'])
            ->and(collect(visibleRowActions($table, $paid))->map->getName()->all())->toBe([])
            ->and($table->getColumn('description')->getAction())->not->toBeNull();
    });

    it('starts every table with a familiar column, never an id', function () {
        $expectedFirst = [
            'Müşteriler' => 'short_name',
            'Açık borçlar' => 'customer.short_name',
            'Ödemeler' => 'customer.short_name',
            'Katalog hizmetleri' => 'name',
            'Paketler' => 'name',
            'Ödeme yöntemleri' => 'name',
            'Kart: hizmetler' => 'name',
            'Kart: iptal edilenler' => 'name',
            'Kart: açık borçlar' => 'description',
            'Kart: son ödemeler' => 'paid_on',
        ];

        foreach (everyTable() as $label => [$class, $parameters]) {
            $columns = rulesTable($class, $parameters)->getColumns();
            $names = collect($columns)->flatMap(fn (mixed $column): array => $column instanceof Stack
                ? array_keys($column->getColumns())
                : [$column->getName()]);

            expect($names)->not->toContain('id', "{$label} shows an id column");

            if (isset($expectedFirst[$label])) {
                expect($names->first())->toBe($expectedFirst[$label], "{$label} does not start with a familiar column");
            }
        }
    });

    it('right-aligns every money column', function () {
        $found = 0;

        foreach (everyTable() as $label => [$class, $parameters]) {
            foreach (rulesTable($class, $parameters)->getColumns() as $column) {
                if ($column instanceof MoneyColumn) {
                    $found++;

                    expect($column->getAlignment())->toBe(Alignment::End, "{$label}: {$column->getName()} is not right-aligned");
                }
            }
        }

        expect($found)->toBeGreaterThanOrEqual(8);
    });
});

describe('panels', function () {
    it('names the section of rarer actions after the record and never calls it "Diğer"', function () {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->for($customer)->create();
        $service = CustomerService::factory()->for($customer)->create();
        $contact = Contact::factory()->for($customer)->create();
        $catalog = CatalogService::factory()->create();
        $package = Package::factory()->withCatalogServices()->create();
        $method = PaymentMethod::query()->firstOrFail();
        $card = ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class];

        $panels = [
            'Borç işlemleri' => Livewire::test(ChargesRelationManager::class, $card)->mountAction(TestAction::make('editCharge')->table($charge)),
            'Hizmet işlemleri' => Livewire::test(CustomerServicesRelationManager::class, $card)->mountAction(TestAction::make('editService')->table($service)),
            'Kişi işlemleri' => Livewire::test(ContactsRelationManager::class, $card)->mountAction(TestAction::make('edit')->table($contact)),
            'Katalog hizmeti işlemleri' => Livewire::test(ListCatalogServices::class)->mountAction(TestAction::make('edit')->table($catalog)),
            'Paket işlemleri' => Livewire::test(ListPackages::class)->mountAction(TestAction::make('edit')->table($package)),
            'Ödeme yöntemi işlemleri' => Livewire::test(ManagePaymentMethods::class)->mountAction(TestAction::make('edit')->table($method)),
        ];

        foreach ($panels as $heading => $panel) {
            $panel->assertSchemaComponentExists(
                RecordActionsSection::ACTIONS_KEY,
                'mountedActionSchema0',
                checkComponentUsing: fn (Actions $actions): bool => $actions->getContainer()->getParentComponent()?->getHeading() === $heading,
            );

            expect($heading)->not->toContain('Diğer');
        }
    });

    it('lays the resource forms out in one column, keeping the two-column sections inside them (T-84)', function () {
        // ListRecords and ManageRecords give a form without its own columns a two-column grid from `lg`, and a
        // Section takes one of those columns: the catalog form's section was squeezed into half the panel.
        $catalog = CatalogService::factory()->create();
        $package = Package::factory()->withCatalogServices()->create();
        $method = PaymentMethod::query()->firstOrFail();

        $forms = [
            'catalog create' => Livewire::test(ListCatalogServices::class)->mountAction('create'),
            'catalog edit' => Livewire::test(ListCatalogServices::class)->mountAction(TestAction::make('edit')->table($catalog)),
            'package create' => Livewire::test(ListPackages::class)->mountAction('create'),
            'package edit' => Livewire::test(ListPackages::class)->mountAction(TestAction::make('edit')->table($package)),
            'payment method create' => Livewire::test(ManagePaymentMethods::class)->mountAction('create'),
            'payment method edit' => Livewire::test(ManagePaymentMethods::class)->mountAction(TestAction::make('edit')->table($method)),
        ];

        foreach ($forms as $name => $component) {
            $schema = $component->instance()->getSchema('mountedActionSchema0');

            expect($schema->getColumns('lg'))->toBe(1, "{$name}: the form's own grid is one column");
        }

        foreach (['catalog create', 'package create'] as $name) {
            $section = $forms[$name]->instance()->getSchema('mountedActionSchema0')->getComponents()[0];

            expect($section)->toBeInstanceOf(Section::class)
                ->and($section->getColumns('lg'))->toBe(2, "{$name}: the section keeps its two columns");
        }
    });

    it('keeps the panel footer to "Vazgeç" then "Kaydet", at the bottom right, with no danger action beside it', function () {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->for($customer)->create();
        $service = CustomerService::factory()->for($customer)->create();
        $contact = Contact::factory()->for($customer)->create();
        $card = ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class];

        $mounted = [
            Livewire::test(ChargesRelationManager::class, $card)->mountAction(TestAction::make('editCharge')->table($charge)),
            Livewire::test(CustomerServicesRelationManager::class, $card)->mountAction(TestAction::make('editService')->table($service)),
            Livewire::test(ContactsRelationManager::class, $card)->mountAction(TestAction::make('edit')->table($contact)),
            Livewire::test(ListCatalogServices::class)->mountAction('create'),
            Livewire::test(ListPackages::class)->mountAction('create'),
            Livewire::test(ManagePaymentMethods::class)->mountAction('create'),
            Livewire::test(ListCustomers::class)->mountAction('createCustomer'),
            Livewire::test(TopbarActions::class)->mountAction('recordPayment'),
        ];

        foreach ($mounted as $component) {
            $action = $component->instance()->getMountedAction();

            expectFormWindow($action);

            expect(array_keys($action->getModalFooterActions()))->toBe(['submit', 'cancel'])
                ->and($action->getModalFooterActionsAlignment())->toBe(Alignment::End)
                ->and($action->getModalCancelActionLabel())->toBe('Vazgeç')
                ->and($action->getModalSubmitActionLabel())->toBe('Kaydet');
        }
    });

    it('opens every create and edit form in the centered form window, and a confirmation in the small dialog (T-84)', function () {
        // Decision change (T-84, docs/UI.md §2 Kural 8): this replaced "opens every create and edit form in a slide-over".
        $customer = Customer::factory()->create();
        $card = ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class];

        $actions = [
            CreateCustomerAction::make(),
            EditCustomerAction::make(),
            RecordPaymentAction::make(),
            AssignCustomerServiceAction::make()->customer($customer),
        ];

        $mounted = [
            Livewire::test(ListCatalogServices::class)->mountAction(TestAction::make('edit')->table(CatalogService::factory()->create())),
            Livewire::test(ListPackages::class)->mountAction(TestAction::make('edit')->table(Package::factory()->withCatalogServices()->create())),
            Livewire::test(ManagePaymentMethods::class)->mountAction(TestAction::make('edit')->table(PaymentMethod::query()->firstOrFail())),
            Livewire::test(ContactsRelationManager::class, $card)->mountAction(TestAction::make('create')->table()),
            Livewire::test(ChargesRelationManager::class, $card)->mountAction(TestAction::make('editCharge')->table(Charge::factory()->for($customer)->create())),
            Livewire::test(CustomerServicesRelationManager::class, $card)->mountAction(TestAction::make('editService')->table(CustomerService::factory()->for($customer)->create())),
        ];

        foreach ([...$actions, ...array_map(fn ($component): Action => $component->instance()->getMountedAction(), $mounted)] as $action) {
            expectFormWindow($action);
        }

        $confirmation = Livewire::test(PaymentsRelationManager::class, $card)
            ->mountAction(TestAction::make('deletePayment')->table(Payment::factory()->for($customer)->create()))
            ->instance()->getMountedAction();

        expect($confirmation->isConfirmationRequired())->toBeTrue()
            ->and($confirmation->getModalWidth())->not->toBe(AppServiceProvider::FORM_WINDOW_CLASS)
            ->and($confirmation->isModalHeaderSticky())->toBeFalse()
            ->and($confirmation->isModalFooterSticky())->toBeFalse();
    });

    it('opens the table filters in a centered window with a sticky header and footer, not in a slide-over (T-84)', function () {
        foreach ([ListCharges::class, ListPayments::class] as $page) {
            $table = rulesTable($page);
            $trigger = $table->getFiltersTriggerAction();

            expect($table->getFiltersLayout())->toBe(FiltersLayout::Modal, $page)
                ->and($trigger->isModalSlideOver())->toBeFalse()
                ->and($trigger->isModalHeaderSticky())->toBeTrue()
                ->and($trigger->isModalFooterSticky())->toBeTrue();
        }
    });

    it('marks the dangerous actions red and gives each a confirmation or a form of its own', function () {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->for($customer)->create();
        $service = CustomerService::factory()->for($customer)->create();
        $contact = Contact::factory()->for($customer)->create();
        $catalog = CatalogService::factory()->create();
        $card = ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class];

        $dangerous = [
            Livewire::test(ChargesRelationManager::class, $card)->mountAction(sectionAction(TestAction::make('editCharge')->table($charge), 'cancelCharge', danger: true)),
            Livewire::test(CustomerServicesRelationManager::class, $card)->mountAction(sectionAction(TestAction::make('editService')->table($service), 'cancelService', danger: true)),
            Livewire::test(CustomerServicesRelationManager::class, $card)->mountAction(sectionAction(TestAction::make('editService')->table(CustomerService::factory()->for($customer)->create()), 'delete', danger: true)),
            Livewire::test(ContactsRelationManager::class, $card)->mountAction(sectionAction(TestAction::make('edit')->table($contact), 'delete', danger: true)),
            Livewire::test(ListCatalogServices::class)->mountAction(sectionAction(TestAction::make('edit')->table($catalog), 'delete', danger: true)),
            Livewire::test(PaymentsRelationManager::class, $card)->mountAction(TestAction::make('deletePayment')->table(Payment::factory()->for($customer)->create())),
        ];

        foreach ($dangerous as $component) {
            $action = $component->instance()->getMountedAction();

            expect($action->getColor())->toBe('danger')
                ->and($action->isConfirmationRequired() || $action->hasFormWrapper())->toBeTrue();
        }
    });

    it('offers the rare actions only through the section, never as a footer button beside Kaydet', function () {
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->create();

        $panel = Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->mountAction(TestAction::make('edit')->table($contact));

        expect($panel->instance()->getMountedAction()->getExtraModalFooterActions())->toBe([]);
    });
});

describe('empty tables', function () {
    it('tells what to do and shows the button that does it, in every list', function () {
        $expected = [
            'Müşteriler' => 'createCustomerEmpty',
            'Açık borçlar' => null,
            'Ödemeler' => 'recordPaymentEmpty',
            'Katalog hizmetleri' => 'createEmpty',
            'Paketler' => 'createEmpty',
            'Ödeme yöntemleri' => 'createEmpty',
            'Kart: hizmetler' => 'assignServiceEmpty',
            'Kart: açık borçlar' => null,
            'Kart: son ödemeler' => 'recordPaymentEmpty',
            'Kart: kişiler' => 'createEmpty',
        ];

        foreach (everyTable() as $label => [$class, $parameters]) {
            if (! array_key_exists($label, $expected)) {
                continue;
            }

            $table = rulesTable($class, $parameters);
            $actionNames = collect($table->getEmptyStateActions())->map->getName()->all();

            expect($table->getEmptyStateHeading())->not->toBeEmpty("{$label} has no empty heading")
                ->and($table->getEmptyStateDescription())->not->toBeEmpty("{$label} has no empty description");

            // Charge lists have no button: charges only come from the rules (G-5B.3).
            if ($expected[$label] === null) {
                expect($actionNames)->toBeEmpty("{$label} should have no empty state action");
            } else {
                expect($actionNames)->toContain($expected[$label]);
            }
        }
    });

    it('shows the empty state action button in the rendered empty table', function () {
        PaymentMethod::query()->delete();

        Livewire::test(ManagePaymentMethods::class)
            ->assertSee(__('payment_methods.empty_heading'))
            ->assertSee(__('payment_methods.add'));

        Livewire::test(ListCatalogServices::class)
            ->assertSee(__('catalog_services.empty_heading'))
            ->assertSee(__('catalog_services.add'));
    });
});

describe('default filters', function () {
    it('shows the default filter as a visible chip', function () {
        Charge::factory()->create();
        CatalogService::factory()->create();

        Livewire::test(ListCharges::class)->assertSee('Durum: Açık');

        foreach ([ListCustomers::class, ListCatalogServices::class, ListPackages::class, ManagePaymentMethods::class] as $page) {
            Livewire::test($page)->assertSee(__('archive.filter').': '.__('archive.filter_not_archived'));
        }
    });
});

describe('removed pages', function () {
    it('has no create or edit page for customers, catalog services and packages', function () {
        foreach ([CustomerResource::class, CatalogServiceResource::class, PackageResource::class] as $resource) {
            expect($resource::hasPage('create'))->toBeFalse()
                ->and($resource::hasPage('edit'))->toBeFalse();
        }

        expect(Route::has('filament.panel.resources.customers.create'))->toBeFalse()
            ->and(Route::has('filament.panel.resources.customers.edit'))->toBeFalse()
            ->and(Route::has('filament.panel.resources.catalog-services.create'))->toBeFalse()
            ->and(Route::has('filament.panel.resources.catalog-services.edit'))->toBeFalse()
            ->and(Route::has('filament.panel.resources.packages.create'))->toBeFalse()
            ->and(Route::has('filament.panel.resources.packages.edit'))->toBeFalse();
    });

    it('links to no removed page from the pages people use', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        Contact::factory()->for($customer)->primary()->create();
        Charge::factory()->for($customer)->create(['due_date' => '2026-06-01']);
        CatalogService::factory()->create();
        Package::factory()->withCatalogServices()->create();

        $urls = [
            Dashboard::getUrl(),
            CustomerResource::getUrl('index'),
            CustomerResource::getUrl('view', ['record' => $customer]),
            CatalogServiceResource::getUrl('index'),
            PackageResource::getUrl('index'),
        ];

        foreach ($urls as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            expect($html)->not->toMatch('#href="[^"]*/(customers|catalog-services|packages)(/[^"/]+)?/(create|edit)"#');
        }
    });

    it('opens the customer card from a Ctrl+K result', function () {
        $customer = Customer::factory()->create();

        expect(CustomerResource::getGlobalSearchResultUrl($customer))->toBe(CustomerResource::getUrl('view', ['record' => $customer]));
    });
});

describe('error messages', function () {
    it('carries a neutral reason code on the domain exceptions and keeps the messages', function () {
        expect(InvalidCharge::hasAllocations()->reason)->toBe(ChargeErrorReason::HasAllocations)
            ->and(InvalidCharge::invalidSplitAmount()->reason)->toBe(ChargeErrorReason::InvalidSplitAmount)
            ->and(InvalidCharge::cancelReasonRequired()->getMessage())->toBe(__('charges.cancel_reason_required'))
            ->and(InvalidCustomerService::endDateNotInFuture()->reason)->toBe(CustomerServiceErrorReason::EndDateNotInFuture)
            ->and(InvalidCustomerService::customerArchived()->getMessage())->toBe(__('customer_services.customer_archived'));
    });

    it('maps a reason to its form field only in the UI layer', function () {
        expect(FieldError::fieldFor(ChargeErrorReason::HasAllocations))->toBe('amount')
            ->and(FieldError::fieldFor(ChargeErrorReason::InvalidSplitAmount))->toBe('amount')
            ->and(FieldError::fieldFor(ChargeErrorReason::CancelReasonRequired))->toBe('reason')
            ->and(FieldError::fieldFor(CustomerServiceErrorReason::PriceNotPositive))->toBe('price')
            ->and(FieldError::fieldFor(CustomerServiceErrorReason::UpcomingChargeExists))->toBe('end_date')
            ->and(FieldError::fieldFor(CustomerServiceErrorReason::PeriodAlreadyEnded))->toBe('start_date')
            ->and(FieldError::fieldFor(CustomerServiceErrorReason::CustomerArchived))->toBeNull()
            ->and(FieldError::fieldFor(ChargeErrorReason::NotOpen))->toBeNull()
            ->and(FieldError::fieldFor(QuoteErrorReason::RecipientRequired))->toBe('recipient_name')
            ->and(FieldError::fieldFor(QuoteErrorReason::RecipientEmailInvalid))->toBe('recipient_email')
            ->and(FieldError::fieldFor(QuoteErrorReason::NoRecipient))->toBe('recipient_email')
            ->and(FieldError::fieldFor(QuoteErrorReason::ItemRequired))->toBe('items')
            ->and(FieldError::fieldFor(QuoteErrorReason::ShortNameTaken))->toBe('short_name')
            ->and(FieldError::fieldFor(QuoteErrorReason::Expired))->toBeNull();

        expect((new ReflectionClass(InvalidCharge::class))->hasMethod('field'))->toBeFalse()
            ->and((new ReflectionClass(InvalidCustomerService::class))->hasMethod('field'))->toBeFalse()
            ->and((new ReflectionClass(InvalidQuote::class))->hasMethod('field'))->toBeFalse();
    });

    it('shows a split refusal under the amount field, not as a notification', function () {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->for($customer)->create(['amount' => 100000]);

        Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->mountAction(sectionAction(TestAction::make('editCharge')->table($charge), 'splitCharge'))
            ->fillForm(['amount' => '1.000', 'due_date' => '2026-09-01'])
            ->callMountedAction()
            ->assertHasFormErrors(['amount']);

        expect($charge->refresh()->amount)->toBe(100000);
    });

    it('keeps a refusal that belongs to no field as a notification', function () {
        $customer = Customer::factory()->archived()->create();
        $service = CustomerService::factory()->for($customer)->create(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

        Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->mountAction(TestAction::make('reactivate')->table($service))
            ->callMountedAction()
            ->assertNotified('Önce müşteriyi arşivden çıkarın.');
    });

    it('words every validation message as a problem and how to fix it', function () {
        $messages = collect(trans('validation'))
            ->except(['custom', 'attributes'])
            ->flatMap(fn (mixed $message): array => (array) $message);

        expect($messages)->not->toBeEmpty();

        foreach ($messages as $message) {
            expect($message)->toContain('; ')->and($message)->toEndWith('.');
        }

        expect(trans('validation.unique'))->toBe('Bu :attribute kullanılıyor; başka bir :attribute yazın.')
            ->and(trans('validation.required'))->toBe(':attribute boş bırakılamaz; bir değer yazın veya seçin.')
            ->and(trans('validation.email'))->toBe(':attribute geçerli bir e-posta adresi değil; ad@ornek.com biçiminde yazın.')
            ->and(trans('validation.max.string'))->toBe(':attribute çok uzun; en fazla :max karakter yazın.');
    });
});
