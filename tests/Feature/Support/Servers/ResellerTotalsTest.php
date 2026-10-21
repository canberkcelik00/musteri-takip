<?php

use App\Enums\RemoteServerErrorReason;
use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Support\Servers\ServerApiClient;
use Illuminate\Support\Facades\Http;

function whmFixtureBody(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/whm/{$name}.json")), true);
}

/**
 * Fakes the four WHM calls the totals use; a value other than a fixture name is a ready response.
 *
 * @param  array<string, mixed>  $overrides  call name => response
 */
function fakeResellerCalls(array $overrides = []): void
{
    $responses = [];

    foreach (['resellerstats', 'acctcounts', 'listaccts', 'showbw'] as $call) {
        $responses["*{$call}*"] = $overrides[$call] ?? Http::response(whmFixtureBody($call));
    }

    Http::fake($responses);
}

function deniedResponse(): mixed
{
    return Http::response(['metadata' => ['result' => 0, 'reason' => 'Access denied', 'command' => 'resellerstats']]);
}

function resellerServer(): Server
{
    return Server::factory()->whm()->create(['hostname' => 'sunucu.example.com', 'username' => 'reseller']);
}

it('reads the totals and limits of a reseller in bytes', function () {
    fakeResellerCalls();

    $totals = ServerApiClient::for(resellerServer())->resellerTotals();

    expect($totals->diskBytes)->toBe(40960 * 1048576)
        ->and($totals->diskLimitBytes)->toBe(51200 * 1048576)
        ->and($totals->bandwidthBytes)->toBe(122880 * 1048576)
        ->and($totals->bandwidthLimitBytes)->toBeNull()
        ->and($totals->transferLimitsRead)->toBeTrue()
        ->and($totals->accounts)->toBe(24)
        ->and($totals->accountsLimit)->toBe(30)
        ->and($totals->accountsLimitRead)->toBeTrue();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'resellerstats') && str_contains($request->url(), 'user=reseller'));
});

it('treats a zero limit as unlimited', function () {
    fakeResellerCalls(['acctcounts' => Http::response(['data' => ['reseller' => ['active' => 5, 'suspended' => 0, 'limit' => 0]], 'metadata' => ['result' => 1]])]);

    $totals = ServerApiClient::for(resellerServer())->resellerTotals();

    expect($totals->accounts)->toBe(5)
        ->and($totals->accountsLimit)->toBeNull()
        ->and($totals->accountsLimitRead)->toBeTrue();
});

it('sums the totals from the account list when the reseller statistics are refused and says the limits are unknown', function () {
    fakeResellerCalls(['resellerstats' => deniedResponse(), 'acctcounts' => deniedResponse()]);

    $totals = ServerApiClient::for(resellerServer())->resellerTotals();

    expect($totals->diskBytes)->toBe((512 + 1536) * 1048576)
        ->and($totals->diskLimitBytes)->toBeNull()
        ->and($totals->bandwidthBytes)->toBe(3 * 1073741824)
        ->and($totals->bandwidthLimitBytes)->toBeNull()
        ->and($totals->transferLimitsRead)->toBeFalse()
        ->and($totals->accounts)->toBe(2)
        ->and($totals->accountsLimit)->toBeNull()
        ->and($totals->accountsLimitRead)->toBeFalse();
});

it('keeps the disk and bandwidth limits when only the account counts are refused', function () {
    fakeResellerCalls(['acctcounts' => deniedResponse()]);

    $totals = ServerApiClient::for(resellerServer())->resellerTotals();

    expect($totals->diskLimitBytes)->toBe(51200 * 1048576)
        ->and($totals->transferLimitsRead)->toBeTrue()
        ->and($totals->accounts)->toBe(2)
        ->and($totals->accountsLimitRead)->toBeFalse();
});

it('does not fall back when the token is not authorized', function () {
    fakeResellerCalls(['resellerstats' => Http::response('denied', 401)]);

    expect(fn () => ServerApiClient::for(resellerServer())->resellerTotals())->toThrow(
        fn (RemoteServerError $error): bool => $error->reason === RemoteServerErrorReason::Unauthorized,
    );

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'listaccts'));
});

it('reads one row per account with its disk, bandwidth, limits and suspension', function () {
    fakeResellerCalls();

    $rows = ServerApiClient::for(resellerServer())->resellerTotals()->toAccounts();

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe([
            'username' => 'logi', 'domain' => 'logi.com.tr', 'plan' => 'gold', 'suspended' => false, 'suspend_reason' => null,
            'disk' => 512 * 1048576, 'disk_limit' => 5000 * 1048576, 'bw' => 1073741824, 'bw_limit' => 10737418240,
        ])
        ->and($rows[1])->toBe([
            'username' => 'askida', 'domain' => 'askida.com', 'plan' => 'mini', 'suspended' => true, 'suspend_reason' => 'Odeme yapilmadi',
            'disk' => 1536 * 1048576, 'disk_limit' => null, 'bw' => 2147483648, 'bw_limit' => null,
        ]);
});

it('still gives the account rows when the reseller statistics are refused', function () {
    fakeResellerCalls(['resellerstats' => deniedResponse(), 'acctcounts' => deniedResponse()]);

    expect(ServerApiClient::for(resellerServer())->resellerTotals()->toAccounts())->toHaveCount(2);
});

it('leaves the bandwidth of the accounts unknown when it cannot be read', function () {
    fakeResellerCalls(['showbw' => deniedResponse()]);

    $totals = ServerApiClient::for(resellerServer())->resellerTotals();

    expect($totals->toAccounts()[0])->toMatchArray(['bw' => null, 'bw_limit' => null, 'disk' => 512 * 1048576])
        ->and($totals->bandwidthBytes)->toBe(122880 * 1048576);
});

it('fails when neither the statistics nor the account list can be read', function () {
    fakeResellerCalls(['resellerstats' => deniedResponse(), 'listaccts' => deniedResponse()]);

    expect(fn () => ServerApiClient::for(resellerServer())->resellerTotals())->toThrow(
        fn (RemoteServerError $error): bool => $error->reason === RemoteServerErrorReason::InvalidResponse,
    );
});

it('stores the totals as resources with a limit_read mark', function () {
    fakeResellerCalls();

    $resources = ServerApiClient::for(resellerServer())->resellerTotals()->toResources();

    expect(array_column($resources, 'id'))->toBe(['reseller_disk', 'reseller_bandwidth', 'reseller_accounts'])
        ->and($resources[0])->toMatchArray(['used' => 40960 * 1048576, 'limit' => 51200 * 1048576, 'is_bytes' => true, 'limit_read' => true])
        ->and($resources[2])->toMatchArray(['used' => 24, 'limit' => 30, 'is_bytes' => false, 'limit_read' => true]);
});
