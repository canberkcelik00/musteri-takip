<?php

use App\Actions\Servers\PreviewServerPull;
use App\Actions\Servers\PullFromServer;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00:00'));

function fakeWhm(): void
{
    Http::fake([
        '*/json-api/listpkgs*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listpkgs.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/listaccts*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listaccts.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/uapi_cpanel*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/uapi_list_domains.json')), 200, ['Content-Type' => 'application/json']),
    ]);
}

it('counts new, linkable, linked and suspended accounts and new packages without writing anything', function () {
    fakeWhm();
    $server = Server::factory()->whm()->create();
    CatalogService::factory()->ofType(ServiceType::Hosting)->create(['server_id' => $server->id, 'remote_package' => 'gold']);
    $customerService = CustomerService::factory()->forCatalogService(CatalogService::factory()->ofType(ServiceType::Hosting)->create())->create();
    $customerService->items()->sole()->update(['details' => ['domain' => 'LOGI.com.tr']]);
    $before = [Customer::query()->count(), CatalogService::query()->count(), CustomerServiceItem::query()->count()];

    $preview = app(PreviewServerPull::class)->handle($server);

    expect($preview->error)->toBeNull()
        ->and($preview->newPackages)->toBe(['mini'])
        ->and([$preview->newAccounts, $preview->linkableAccounts, $preview->alreadyLinkedAccounts, $preview->suspendedAccounts])->toBe([0, 1, 0, 1])
        ->and([Customer::query()->count(), CatalogService::query()->count(), CustomerServiceItem::query()->count()])->toBe($before)
        ->and($server->fresh()->last_pulled_at)->toBeNull();
});

it('agrees with the pull that follows it', function () {
    fakeWhm();
    $server = Server::factory()->whm()->create();
    $prices = [
        'gold' => ['price' => 100000, 'currency' => Currency::Try],
        'mini' => ['price' => 50000, 'currency' => Currency::Try],
    ];

    $preview = app(PreviewServerPull::class)->handle($server);
    $result = app(PullFromServer::class)->handle($server, $prices);

    expect($result->error)->toBeNull()
        ->and($result->packages->created)->toBe($preview->newPackages === [] ? [] : ['gold', 'mini'])
        ->and(count($result->accounts->created))->toBe($preview->newAccounts)
        ->and(Charge::query()->count())->toBe(0);

    $again = app(PreviewServerPull::class)->handle($server);

    expect([$again->newPackages, $again->newAccounts, $again->alreadyLinkedAccounts])->toBe([[], 0, 1]);
});

it('turns a server failure into the preview error', function () {
    Http::fake(['*' => Http::response('no', 401)]);

    $preview = app(PreviewServerPull::class)->handle(Server::factory()->whm()->create());

    expect($preview->error)->toBe(__('servers.connection_unauthorized'))
        ->and($preview->newPackages)->toBe([]);
});

it('lists every domain of each new account, main domain first, and skips linked and suspended ones', function () {
    fakeWhm();
    $server = Server::factory()->whm()->create();

    $preview = app(PreviewServerPull::class)->handle($server);

    expect($preview->newAccountRows)->toBe([[
        'key' => 'logi',
        'username' => 'logi',
        'domain' => 'logi.com.tr',
        'domains' => ['logi.com.tr', 'logimagaza.com', 'logi.net', 'blog.logi.com.tr', 'logimagaza.logi.com.tr'],
    ]]);

    Http::assertSentCount(3);
});

it('asks no domains for accounts that are not new', function () {
    fakeWhm();
    $server = Server::factory()->whm()->create();
    app(PullFromServer::class)->handle($server, [
        'gold' => ['price' => 100000, 'currency' => Currency::Try],
        'mini' => ['price' => 50000, 'currency' => Currency::Try],
    ]);
    fakeWhm();

    $preview = app(PreviewServerPull::class)->handle($server);

    expect($preview->newAccountRows)->toBe([]);

    Http::assertSentCount(2);
});

it('falls back to the main domain when the domains cannot be read and stops asking after a timeout', function () {
    $accounts = ['acct' => [
        ['user' => 'bir', 'domain' => 'bir.com', 'email' => 'a@bir.com', 'plan' => 'gold', 'unix_startdate' => 1700000000, 'suspended' => 0],
        ['user' => 'iki', 'domain' => 'iki.com', 'email' => 'a@iki.com', 'plan' => 'gold', 'unix_startdate' => 1700000000, 'suspended' => 0],
    ]];
    $domainRequests = 0;
    Http::fake([
        '*/json-api/listpkgs*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listpkgs.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/listaccts*' => Http::response(['data' => $accounts, 'metadata' => ['result' => 1]]),
        '*/json-api/uapi_cpanel*' => function () use (&$domainRequests): never {
            $domainRequests++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        },
    ]);

    $preview = app(PreviewServerPull::class)->handle(Server::factory()->whm()->create());

    expect($preview->error)->toBeNull()
        ->and(array_column($preview->newAccountRows, 'domains'))->toBe([['bir.com'], ['iki.com']])
        ->and($domainRequests)->toBe(1);
});
