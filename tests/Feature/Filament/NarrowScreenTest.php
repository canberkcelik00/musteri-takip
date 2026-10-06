<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Narrow-screen layout of the lists and the topbar search (G-4B.7, docs/UI.md §2 Kural 5, §5): no
 * list scrolls sideways from 375 px. The tests read the column rules and the markup; the widths
 * themselves are measured in a real browser (docs/DECISIONS.md T-85).
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

// --- Ödemeler -----------------------------------------------------------

it('wraps the payment list\'s customer, shows the method from md and the note from lg', function () {
    $table = Livewire::test(ListPayments::class)->instance()->getTable();

    expect($table->getColumn('customer.short_name')->canWrap())->toBeTrue()
        ->and($table->getColumn('customer.short_name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('paid_on')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('amount')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('paymentMethod.name')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('note')->getVisibleFrom())->toBe('lg')
        ->and($table->getColumn('note')->canWrap())->toBeTrue();
});

it('hides the payment list\'s "Sil" below md; the amount opens the detail window instead', function () {
    $table = Livewire::test(ListPayments::class)->instance()->getTable();

    expect($table->getAction('deletePayment')->getExtraAttributes())->toBe(['class' => 'fi-hidden-below-md'])
        ->and($table->getColumn('amount')->getAction()->getName())->toBe('viewPayment');
});

/**
 * A payment that paid a charge off in full, so deleting it reopens that charge.
 *
 * @return array{0: Payment, 1: Charge}
 */
function narrowPaymentWithPaidCharge(): array
{
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->paid()->create(['amount' => 50000, 'currency' => Currency::Try]);
    $payment = Payment::factory()->for($customer)->create(['amount' => 50000, 'currency' => Currency::Try]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount' => 50000]);

    return [$payment, $charge];
}

it('opens the payment detail window from the amount without deleting anything; "Sil" is a separate, confirmed action at its end', function () {
    [$payment, $charge] = narrowPaymentWithPaidCharge();
    $view = TestAction::make('viewPayment')->table($payment);
    $delete = sectionAction($view, 'deletePaymentFromDetail', danger: true);

    // Clicking the amount opens the window and deletes nothing, and the old amount-to-delete shortcut is gone.
    $window = Livewire::test(ListPayments::class)->mountAction($view);

    expect($window->instance()->getMountedAction()->getName())->toBe('viewPayment')
        ->and($window->instance()->getTable()->getColumn('amount')->getAction()->getName())->not->toBe('deletePaymentMobile')
        ->and(mountedActionWindowText($window))->toContain('Ödeme işlemleri')
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and($charge->refresh()->status)->toBe(ChargeStatus::Paid);

    // "Sil" lives in the danger row of the window's own section; mounting it only asks for confirmation.
    $list = Livewire::test(ListPayments::class)->mountAction($delete)->assertActionMounted($delete);

    expect($list->instance()->getMountedAction()->isConfirmationRequired())->toBeTrue()
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and($charge->refresh()->status)->toBe(ChargeStatus::Paid);

    $list->callMountedAction()->assertNotified('Ödeme silindi, 1 borç yeniden açıldı.');

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and($charge->refresh()->status)->toBe(ChargeStatus::Open)
        ->and($list->get('mountedActions'))->toBe([]);
});

it('shows the payment\'s date, customer, method, note and allocations in the detail window, collapsed and counted', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    $method = PaymentMethod::factory()->create(['name' => 'Havale/EFT']);
    $payment = Payment::factory()->for($customer)->create([
        'amount' => 100000, 'currency' => Currency::Try, 'paid_on' => '2026-03-05', 'payment_method_id' => $method->id, 'note' => 'Mart ödemesi',
    ]);
    $charge = Charge::factory()->for($customer)->paid()->create(['amount' => 40000, 'currency' => Currency::Try, 'description' => 'Linux Hosting 2026']);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount' => 40000]);

    $window = Livewire::test(ListPayments::class)->mountAction(TestAction::make('viewPayment')->table($payment));

    expect(mountedActionWindowText($window))
        ->toContain('05.03.2026')->toContain('Logi')->toContain('Havale/EFT')->toContain('Mart ödemesi')->toContain('1.000 TL')
        ->toContain('Dağıtım (1)')->toContain('Linux Hosting 2026')->toContain('400 TL')->toContain('Ödeme')
        ->toContain('600 TL alacak bakiyesine eklendi');

    $allocations = PaymentInfolist::components()[1];

    expect($allocations->isCollapsible())->toBeTrue()
        ->and($allocations->isCollapsed())->toBeTrue();
});

it('says nothing about the credit balance when the whole payment went to charges, and hides the allocations without any', function () {
    [$full] = narrowPaymentWithPaidCharge();

    expect(PaymentInfolist::creditAddedText($full))->toBeNull();

    $onlyCredit = Payment::factory()->create(['amount' => 25000, 'currency' => Currency::Try]);

    expect(PaymentInfolist::creditAddedText($onlyCredit))->toBe('250 TL alacak bakiyesine eklendi');

    $window = Livewire::test(ListPayments::class)->mountAction(TestAction::make('viewPayment')->table($onlyCredit));

    expect(mountedActionWindowText($window))
        ->toContain('250 TL alacak bakiyesine eklendi')
        ->not->toContain('Dağıtım (');
});

// --- Katalog hizmetleri ---------------------------------------------------

it('wraps the catalog name, which opens the edit window at every width; type and badge show from md', function () {
    $table = Livewire::test(ListCatalogServices::class)->instance()->getTable();

    expect($table->getColumn('name')->canWrap())->toBeTrue()
        ->and($table->getColumn('name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('name')->getAction()->getName())->toBe('editCatalogService')
        ->and($table->getColumn('name')->getExtraAttributes())->toBe(['class' => 'fi-ta-name-link'])
        ->and($table->getColumn('type')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('price')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('archived_at')->getVisibleFrom())->toBe('md');
});

it('hides the catalog row\'s "Düzenle" below md, where the name is the way in', function () {
    $table = Livewire::test(ListCatalogServices::class)->instance()->getTable();

    expect($table->getAction('edit')->getExtraAttributes())->toBe(['class' => 'fi-hidden-below-md']);
});

it('edits a catalog service through its name, with the same form and rare actions as the row button', function () {
    $service = CatalogService::factory()->create(['name' => 'Eski ad']);
    $panel = TestAction::make('editCatalogService')->table($service);

    Livewire::test(ListCatalogServices::class)
        ->mountAction($panel)
        ->assertSchemaStateSet(['name' => 'Eski ad'])
        ->fillForm(['name' => 'Yeni ad'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($service->refresh()->name)->toBe('Yeni ad');

    $list = Livewire::test(ListCatalogServices::class);

    expect(actionOffered($list, sectionAction($panel, 'archive')))->toBeTrue();

    $list->callAction(sectionAction($panel, 'archive'));

    expect($service->refresh()->isArchived())->toBeTrue();
});

// --- Paketler -------------------------------------------------------------

it('wraps the package name; the included services and the badge show from md', function () {
    $table = Livewire::test(ListPackages::class)->instance()->getTable();

    expect($table->getColumn('name')->canWrap())->toBeTrue()
        ->and($table->getColumn('name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('catalogServices.name')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('price')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('archived_at')->getVisibleFrom())->toBe('md');

    $package = Package::factory()->withCatalogServices()->create();

    expect(collect($table->getFlatRecordActions())->keys()->all())->toBe(['edit'])
        ->and($table->getAction('edit')->record($package)->getExtraAttributes())->toBe([]);
});

// --- Üst çubuk araması ----------------------------------------------------

/**
 * The markup of the first element with the given class and its opening tag, from the page HTML.
 */
function searchButtonTag(string $html, string $needle): string
{
    preg_match('/<button\b[^>]*'.preg_quote($needle, '/').'[^>]*>/s', $html, $match);

    return $match[0] ?? '';
}

it('gives narrow screens a text "Ara" button that points at the search and starts closed', function () {
    $html = $this->get(Dashboard::getUrl())->assertOk()->getContent();

    $button = searchButtonTag($html, 'aria-controls="fi-topbar-search"');

    expect($button)->not->toBe('')
        ->and($button)->toContain('aria-expanded="false"')
        ->and($button)->toContain('x-bind:aria-expanded')
        ->and($html)->toMatch('/class="fi-search-open".*?\bAra\s*(?:<!--.*?-->\s*)*<\/button>/s');
});

// Filament draws a gray button without any color class (T-80); the theme's secondary look is
// `.fi-btn:not(.fi-color)`, the primary one needs `fi-color-primary`.
it('draws the "Ara" button as a secondary (gray) button, never the primary one', function () {
    $html = $this->get(Dashboard::getUrl())->assertOk()->getContent();

    $button = searchButtonTag($html, 'aria-controls="fi-topbar-search"');

    expect($button)->toContain('fi-btn')
        ->and($button)->not->toContain('fi-color');
});

it('opens the narrow search with the panel\'s own shortcut, beside Filament\'s Mousetrap binding rather than over it', function () {
    $html = $this->get(Dashboard::getUrl())->assertOk()->getContent();

    $start = strpos($html, 'class="fi-search-open"');
    $component = substr($html, $start, strpos($html, '</button>', $start) - $start);

    // Mousetrap keeps one handler per key, so a second `x-mousetrap` for Ctrl+K would replace
    // Filament's: this one is a plain keydown listener that reads the key from the panel's config.
    expect($component)->toContain('x-on:keydown.window="onShortcut($event)"')
        ->toContain('mod+k')
        ->not->toContain('x-mousetrap');
});

it('puts a text "Kapat" beside the search field, inside the search container', function () {
    $html = $this->get(Dashboard::getUrl())->assertOk()->getContent();

    expect($html)->toMatch('/class="fi-global-search-ctn".*?fi-global-search-field.*?<button[^>]*fi-search-close[^>]*>.*?Kapat/s');
});
