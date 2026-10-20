<?php

use App\Enums\RemoteServerErrorReason;
use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Support\Servers\ServerApiClient;
use App\Support\Servers\WhmApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function whmServer(): Server
{
    return Server::factory()->whm()->create(['hostname' => 'sunucu.example.com', 'username' => 'reseller', 'api_token' => 'TOK123']);
}

/**
 * @return array<string, mixed>
 */
function serverFixture(string $path): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/{$path}")), true);
}

it('builds a WHM client for a WHM server', function () {
    expect(ServerApiClient::for(whmServer()))->toBeInstanceOf(WhmApi::class);
});

it('lists packages with their limits as reported', function () {
    Http::fake(['*' => Http::response(serverFixture('whm/listpkgs.json'))]);

    $packages = ServerApiClient::for(whmServer())->packages();

    expect($packages)->toHaveCount(2)
        ->and($packages[0]->name)->toBe('gold')
        ->and($packages[0]->quota)->toBe('5000')
        ->and($packages[0]->bwlimit)->toBe('unlimited')
        ->and($packages[0]->maxaddon)->toBe('4')
        ->and($packages[1]->quota)->toBe('0');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sunucu.example.com:2087/json-api/listpkgs?api.version=1'
        && $request->header('Authorization') === ['whm reseller:TOK123']);
});

it('lists accounts with suspension, start date and contact email', function () {
    Http::fake(['*' => Http::response(serverFixture('whm/listaccts.json'))]);

    $accounts = ServerApiClient::for(whmServer())->accounts();

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]->key)->toBe('logi')
        ->and($accounts[0]->domain)->toBe('logi.com.tr')
        ->and($accounts[0]->email)->toBe('info@logi.com.tr')
        ->and($accounts[0]->packageName)->toBe('gold')
        ->and($accounts[0]->suspended)->toBeFalse()
        ->and($accounts[0]->startDate->toDateString())->toBe('2023-11-15')
        ->and($accounts[1]->suspended)->toBeTrue()
        ->and($accounts[1]->email)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sunucu.example.com:2087/json-api/listaccts?api.version=1');
});

it('keeps unauthorized, API error, timeout, connection and invalid response apart', function (callable $fake, RemoteServerErrorReason $expected) {
    Http::fake($fake);

    expect(fn () => ServerApiClient::for(whmServer())->ping())->toThrow(
        fn (RemoteServerError $error) => expect($error->reason)->toBe($expected),
    );
})->with([
    '401' => [fn () => Http::response('denied', 401), RemoteServerErrorReason::Unauthorized],
    '403' => [fn () => Http::response('denied', 403), RemoteServerErrorReason::Unauthorized],
    'result 0' => [fn () => Http::response(['metadata' => ['result' => 0, 'reason' => 'x']]), RemoteServerErrorReason::ApiError],
    'unknown body' => [fn () => Http::response(['foo' => 'bar']), RemoteServerErrorReason::InvalidResponse],
    'not json' => [fn () => Http::response('<html>', 200), RemoteServerErrorReason::InvalidResponse],
    'timeout' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out'), RemoteServerErrorReason::Timeout],
    'unreachable' => [fn () => throw new ConnectionException('cURL error 6: Could not resolve host'), RemoteServerErrorReason::ConnectionFailed],
]);

it('rejects a package list without the expected shape', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1], 'data' => []])]);

    expect(fn () => ServerApiClient::for(whmServer())->packages())->toThrow(RemoteServerError::class);
});

it('never puts the token in an error message', function () {
    Http::fake(['*' => Http::response('bad TOK123', 500)]);

    expect(fn () => ServerApiClient::for(whmServer())->ping())->toThrow(
        fn (RemoteServerError $error) => expect($error->getMessage())->not->toContain('TOK123'),
    );
});
