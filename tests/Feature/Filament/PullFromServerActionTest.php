<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Server;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-10-20 10:00:00');
});

function pullWhm(): void
{
    Http::fake([
        '*/json-api/listpkgs*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listpkgs.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/listaccts*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listaccts.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/uapi_cpanel*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/uapi_list_domains.json')), 200, ['Content-Type' => 'application/json']),
    ]);
}

function pullAction(Server $server): TestAction
{
    return TestAction::make('pullFromServer')->table($server);
}

it('shows what a WHM pull would do and asks a price for every new package', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();
    CatalogService::factory()->ofType(ServiceType::Hosting)->create(['server_id' => $server->id, 'remote_package' => 'gold']);

    Livewire::test(ManageServers::class)
        ->mountAction(pullAction($server))
        ->assertSchemaStateSet(['summary' => __('servers.pull_summary', ['new' => 1, 'link' => 0, 'linked' => 0, 'suspended' => 1])])
        ->assertSet('mountedActions.0.data.packages', fn (array $rows): bool => array_values($rows) === [['name' => 'mini', 'price' => null, 'currency' => 'TRY']]);
});

it('pulls a WHM server with the given prices and tells the result in one sentence', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), [
            'packages' => [
                ['name' => 'gold', 'price' => '1.200,00', 'currency' => 'TRY'],
                ['name' => 'mini', 'price' => '25,50', 'currency' => 'USD'],
            ],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('2 paket eklendi, 1 müşteri oluştu, 0 hizmet bağlandı, 1 askıda atlandı');

    expect(CatalogService::query()->where('remote_package', 'gold')->sole()->price)->toBe(120000)
        ->and(CatalogService::query()->where('remote_package', 'mini')->sole()->currency)->toBe(Currency::Usd)
        ->and(Customer::query()->pluck('short_name')->all())->toBe(['logi.com.tr'])
        ->and(CustomerService::query()->count())->toBe(1)
        ->and(Charge::query()->count())->toBe(0)
        ->and($server->fresh()->last_pulled_at)->not->toBeNull();
});

it('requires a price for every new package', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), [
            'packages' => [
                ['name' => 'gold', 'price' => '', 'currency' => 'TRY'],
                ['name' => 'mini', 'price' => '25,50', 'currency' => 'TRY'],
            ],
        ])
        ->assertHasFormErrors(['packages.0.price' => 'required']);

    expect(Customer::query()->count())->toBe(0);
});

it('imports nothing for an account whose package was left without a price', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), [
            'packages' => [
                ['name' => 'gold', 'price' => '0,00', 'currency' => 'TRY'],
                ['name' => 'mini', 'price' => '25,50', 'currency' => 'TRY'],
            ],
        ])
        ->assertNotified();

    expect(Customer::query()->count())->toBe(0);
});

it('asks a start date instead of prices for a cPanel server and puts each domain in its own customer', function () {
    Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/cpanel/list_domains.json')), 200, ['Content-Type' => 'application/json'])]);
    $server = Server::factory()->cpanel()->create();

    Livewire::test(ManageServers::class)
        ->mountAction(pullAction($server))
        ->assertSchemaStateSet(['summary' => __('servers.pull_summary', ['new' => 3, 'link' => 0, 'linked' => 0, 'suspended' => 0])])
        ->assertSet('mountedActions.0.data.packages', [])
        ->fillForm(['start_date' => '2025-12-01'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('3 müşteri oluştu, 0 hizmet bağlandı, 0 askıda atlandı');

    expect(Customer::query()->count())->toBe(3)
        ->and(CustomerService::query()->pluck('start_date')->map->toDateString()->unique()->all())->toBe(['2025-12-01']);
});

it('requires the start date for a cPanel server', function () {
    Http::fake(['*' => Http::response(file_get_contents(base_path('tests/Fixtures/cpanel/list_domains.json')), 200, ['Content-Type' => 'application/json'])]);
    $server = Server::factory()->cpanel()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), [])
        ->assertHasFormErrors(['start_date' => 'required']);
});

it('shows why the server could not be read and imports nothing', function () {
    Http::fake(['*' => Http::response('no', 401)]);
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->mountAction(pullAction($server))
        ->assertSchemaStateSet(['error' => __('servers.connection_unauthorized'), 'summary' => null])
        ->callMountedAction()
        ->assertNotified(__('servers.connection_unauthorized'));

    expect(Customer::query()->count())->toBe(0);
});

function pullAccountRow(string $choice, ?string $custom = null): array
{
    return [
        'key' => 'logi',
        'domains' => ['logi.com.tr', 'logimagaza.com', 'logi.net', 'blog.logi.com.tr', 'logimagaza.logi.com.tr'],
        'account' => 'logi — logi.com.tr',
        'choice' => $choice,
        'custom' => $custom,
    ];
}

/**
 * @return list<array{name: string, price: string, currency: string}>
 */
function pullPackages(): array
{
    return [
        ['name' => 'gold', 'price' => '1.200,00', 'currency' => 'TRY'],
        ['name' => 'mini', 'price' => '25,50', 'currency' => 'TRY'],
    ];
}

it('offers every domain of a new account with the main domain selected', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->mountAction(pullAction($server))
        ->assertSet('mountedActions.0.data.accounts', fn (array $rows): bool => array_values($rows) === [pullAccountRow('logi.com.tr')]);
});

it('creates the customer under the domain picked from the list', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), ['packages' => pullPackages(), 'accounts' => [pullAccountRow('logimagaza.com')]])
        ->assertHasNoFormErrors();

    expect(Customer::query()->pluck('short_name')->all())->toBe(['logimagaza.com']);
});

it('creates the customer under a name typed by hand', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), ['packages' => pullPackages(), 'accounts' => [pullAccountRow('__other', 'Logi Lojistik')]])
        ->assertHasNoFormErrors();

    expect(Customer::query()->pluck('short_name')->all())->toBe(['Logi Lojistik']);
});

it('requires the typed name once the other option is picked', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), ['packages' => pullPackages(), 'accounts' => [pullAccountRow('__other', '')]])
        ->assertHasFormErrors(['accounts.0.custom' => 'required']);

    expect(Customer::query()->count())->toBe(0);
});

it('remembers which accounts were offered so the ones deleted from the list can be left out', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->mountAction(pullAction($server))
        ->assertSet('mountedActions.0.data.offered_accounts', ['logi']);
});

it('does not import an account deleted from the list and says how many were left out', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction(pullAction($server), ['packages' => pullPackages(), 'accounts' => [], 'offered_accounts' => ['logi']])
        ->assertHasNoFormErrors()
        ->assertNotified('2 paket eklendi, 0 müşteri oluştu, 0 hizmet bağlandı, 1 askıda atlandı, 1 hesap çıkarıldı');

    expect(Customer::query()->count())->toBe(0);
});

it('lets accounts be deleted from the list but not the new packages', function () {
    pullWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->mountAction(pullAction($server))
        ->assertSchemaComponentExists('packages', checkComponentUsing: fn (Repeater $repeater): bool => ! $repeater->isDeletable())
        ->assertSchemaComponentExists('accounts', checkComponentUsing: fn (Repeater $repeater): bool => $repeater->isDeletable());
});

it('does not offer the pull for an archived server', function () {
    $server = Server::factory()->archived()->create();

    Livewire::test(ManageServers::class)
        ->filterTable('archived', true)
        ->assertActionHidden(pullAction($server));
});
