<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\CatalogService;
use App\Models\Package;
use App\Models\Payment;
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

it('hides the payment list\'s "Sil" below md; the amount opens the same confirmation', function () {
    $payment = Payment::factory()->create();

    $list = Livewire::test(ListPayments::class);

    expect($list->instance()->getTable()->getAction('deletePayment')->getExtraAttributes())->toBe(['class' => 'fi-hidden-below-md']);

    $list
        ->mountAction(TestAction::make('deletePaymentMobile')->table($payment))
        ->callMountedAction()
        ->assertNotified();

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeFalse();
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

it('puts a text "Kapat" beside the search field, inside the search container', function () {
    $html = $this->get(Dashboard::getUrl())->assertOk()->getContent();

    expect($html)->toMatch('/class="fi-global-search-ctn".*?fi-global-search-field.*?<button[^>]*fi-search-close[^>]*>.*?Kapat/s');
});
