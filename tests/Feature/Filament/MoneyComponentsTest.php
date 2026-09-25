<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Forms\Components\CurrencySelect;
use App\Models\CatalogService;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Fixtures\MoneyComponentsFixture;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('saves "4.000,50" as 400050 and shows it in the same format', function () {
    Livewire::test(MoneyComponentsFixture::class)
        ->fillForm([
            'name' => 'Linux Hosting',
            'type' => ServiceType::Hosting->value,
            'price' => '4.000,50',
            'currency' => Currency::Try->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors([], 'form');

    $service = CatalogService::firstOrFail();

    expect($service->price)->toBe(400050);

    Livewire::test(MoneyComponentsFixture::class, ['record' => $service])
        ->assertFormSet(['price' => '4.000,50'])
        ->assertSee('4.000,50 TL');
});

it('accepts an amount without thousands separator', function () {
    Livewire::test(MoneyComponentsFixture::class)
        ->fillForm([
            'name' => 'Linux Hosting',
            'type' => ServiceType::Hosting->value,
            'price' => '4000,50',
        ])
        ->call('create')
        ->assertHasNoFormErrors([], 'form');

    expect(CatalogService::firstOrFail()->price)->toBe(400050);
});

it('rejects amounts that are not in Turkish format', function (string $input) {
    Livewire::test(MoneyComponentsFixture::class)
        ->fillForm([
            'name' => 'Linux Hosting',
            'type' => ServiceType::Hosting->value,
            'price' => $input,
        ])
        ->call('create')
        ->assertHasFormErrors(['price'], 'form');

    expect(CatalogService::count())->toBe(0);
})->with([
    'dot as decimal' => ['4000.50'],
    'English format' => ['4,000.50'],
    'letters' => ['abc'],
    'negative' => ['-100'],
]);

it('shows the amount error in Turkish', function () {
    Livewire::test(MoneyComponentsFixture::class)
        ->fillForm([
            'name' => 'Linux Hosting',
            'type' => ServiceType::Hosting->value,
            'price' => 'abc',
        ])
        ->call('create')
        ->assertHasFormErrors(['price'], 'form')
        ->assertSee('Tutarı 4.000,50 biçiminde girin.');
});

it('requires an amount', function () {
    Livewire::test(MoneyComponentsFixture::class)
        ->fillForm([
            'name' => 'Linux Hosting',
            'type' => ServiceType::Hosting->value,
            'price' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['price' => 'required'], 'form');
});

it('hides zero cents in the form and the table', function () {
    $service = CatalogService::factory()->create(['price' => 400000, 'currency' => Currency::Usd]);

    Livewire::test(MoneyComponentsFixture::class, ['record' => $service])
        ->assertFormSet(['price' => '4.000', 'currency' => Currency::Usd])
        ->assertTableColumnFormattedStateSet('price', '4.000 USD', $service);
});

it('defaults the currency to TL and offers TL, USD and EUR', function () {
    $select = CurrencySelect::make('currency');

    expect($select->getDefaultState())->toBe('TRY')
        ->and($select->getOptions())->toBe(['TRY' => 'TL', 'USD' => 'USD', 'EUR' => 'EUR']);
});

it('shows the selected currency after the amount', function () {
    Livewire::test(MoneyComponentsFixture::class)
        ->assertSee('TL')
        ->fillForm(['currency' => Currency::Usd->value])
        ->assertSee('USD');
});

it('lists per-currency totals one per line and shows a dash without totals', function () {
    $service = CatalogService::factory()->create(['price' => 400050, 'currency' => Currency::Try]);

    Livewire::test(MoneyComponentsFixture::class, ['record' => $service])
        ->assertSeeInOrder(['4.000,50 TL', '12.000 TL', '350 USD', '12.000 TL + 350 USD', '—', '4.000,50 EUR'])
        ->assertTableColumnFormattedStateSet('price', '4.000,50 TL', $service)
        ->assertSeeInOrder(['10.000 TL', '350 USD'])
        ->assertSee('—');
});

it('shows per-currency totals on one line without commas', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(MoneyComponentsFixture::class, ['record' => $service])
        ->assertTableColumnStateSet('single_line_totals', '12.000 TL + 350 USD', $service)
        ->assertTableColumnStateSet('no_totals', null, $service);
});
