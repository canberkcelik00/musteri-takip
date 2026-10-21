<?php

use App\Actions\Servers\PullFromServer;
use App\Enums\Currency;
use App\Models\Customer;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00:00'));

function pullFixtureServer(): Server
{
    Http::fake([
        '*/json-api/listpkgs*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listpkgs.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/listaccts*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listaccts.json')), 200, ['Content-Type' => 'application/json']),
    ]);

    return Server::factory()->whm()->create();
}

/**
 * @return array<string, array{price: int, currency: Currency}>
 */
function pullFixturePrices(): array
{
    return [
        'gold' => ['price' => 100000, 'currency' => Currency::Try],
        'mini' => ['price' => 50000, 'currency' => Currency::Try],
    ];
}

it('leaves an account the user took off the list out of the import and reports it', function () {
    $server = pullFixtureServer();

    $result = app(PullFromServer::class)->handle($server, pullFixturePrices(), null, [], ['logi']);

    expect($result->error)->toBeNull()
        ->and($result->excludedAccounts)->toBe(['logi'])
        ->and($result->accounts->created)->toBe([])
        ->and(Customer::query()->count())->toBe(0)
        ->and(CustomerServiceItem::query()->count())->toBe(0);
});

it('imports the accounts that stay on the list and lists them again on the next pull', function () {
    $server = pullFixtureServer();

    app(PullFromServer::class)->handle($server, pullFixturePrices(), null, [], ['logi']);
    $again = app(PullFromServer::class)->handle($server, pullFixturePrices());

    expect($again->excludedAccounts)->toBe([])
        ->and($again->accounts->created)->toBe(['logi'])
        ->and(Customer::query()->pluck('short_name')->all())->toBe(['logi.com.tr']);
});

it('does not report a key the server does not list as excluded', function () {
    $server = pullFixtureServer();

    $result = app(PullFromServer::class)->handle($server, pullFixturePrices(), null, [], ['yok']);

    expect($result->excludedAccounts)->toBe([])
        ->and($result->accounts->created)->toBe(['logi']);
});
