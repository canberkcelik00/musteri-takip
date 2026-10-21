<?php

use App\Enums\RemoteServerErrorReason;
use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Support\Servers\RemoteAccount;
use App\Support\Servers\ServerApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, mixed>
 */
function usageFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/cpanel/{$name}.json")), true);
}

/**
 * WHM returns the cPanel UAPI result inside `data.uapi`.
 *
 * @param  array<string, mixed>  $uapi
 * @return array<string, mixed>
 */
function whmWrapped(array $uapi): array
{
    return ['metadata' => ['result' => 1], 'data' => ['uapi' => $uapi]];
}

function usageServerWhm(): Server
{
    return Server::factory()->whm()->create(['hostname' => 'sunucu.example.com', 'username' => 'reseller', 'api_token' => 'TOK123']);
}

function usageServerCpanel(): Server
{
    return Server::factory()->cpanel()->create(['hostname' => 'cp.example.com', 'username' => 'logi', 'api_token' => 'CPTOK']);
}

function usageAccount(string $domain = 'logi.com.tr'): RemoteAccount
{
    return new RemoteAccount($domain, $domain, 'logi', null, null, null, false);
}

it('reads the usage of a WHM account through three UAPI calls', function () {
    Http::fake([
        '*cpanel.function=get_usages*' => Http::response(whmWrapped(usageFixture('get_usages'))),
        '*cpanel.function=list_pops_with_disk*' => Http::response(whmWrapped(usageFixture('list_pops_with_disk'))),
        '*cpanel.function=list_databases*' => Http::response(whmWrapped(usageFixture('list_databases'))),
    ]);

    $usage = ServerApiClient::for(usageServerWhm())->usageOf(usageAccount());

    expect($usage->resources)->toHaveCount(5)
        ->and($usage->mailboxes)->toHaveCount(3)
        ->and($usage->databases)->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sunucu.example.com:2087/json-api/uapi_cpanel?api.version=1&cpanel.user=logi&cpanel.module=ResourceUsage&cpanel.function=get_usages'
        && $request->header('Authorization') === ['whm reseller:TOK123']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sunucu.example.com:2087/json-api/uapi_cpanel?api.version=1&cpanel.user=logi&cpanel.module=Email&cpanel.function=list_pops_with_disk&get_restrictions=1');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sunucu.example.com:2087/json-api/uapi_cpanel?api.version=1&cpanel.user=logi&cpanel.module=Mysql&cpanel.function=list_databases');
});

it('reads resources with their limits, unlimited ones and byte kinds', function () {
    Http::fake(['*' => Http::response(usageFixture('get_usages'))]);

    $usage = ServerApiClient::for(usageServerCpanel())->usageOf(usageAccount());
    $byId = collect($usage->resources)->keyBy('id');

    expect($usage->resources)->toHaveCount(5)
        ->and($byId['disk_usage']->used)->toBe(1288490188)
        ->and($byId['disk_usage']->limit)->toBe(5368709120)
        ->and($byId['disk_usage']->isBytes)->toBeTrue()
        ->and($byId['disk_usage']->label)->toBe('Disk Usage')
        ->and($byId['bandwidth']->limit)->toBeNull()
        ->and($byId['email_accounts']->used)->toBe(4)
        ->and($byId['email_accounts']->limit)->toBe(10)
        ->and($byId['email_accounts']->isBytes)->toBeFalse()
        ->and($byId['mysql_databases']->limit)->toBe(5)
        ->and($byId['addon_domains']->limit)->toBeNull();
});

it('reads mailboxes in bytes, a zero quota as unlimited and the suspension flags', function () {
    Http::fake(['*' => Http::response(usageFixture('list_pops_with_disk'))]);

    $mailboxes = ServerApiClient::for(usageServerCpanel())->usageOf(usageAccount())->mailboxes;

    expect($mailboxes)->toHaveCount(3)
        ->and($mailboxes[0]->address)->toBe('info@logi.com.tr')
        ->and($mailboxes[0]->domain)->toBe('logi.com.tr')
        ->and($mailboxes[0]->usedBytes)->toBe(481820672)
        ->and($mailboxes[0]->quotaBytes)->toBe(1073741824)
        ->and($mailboxes[0]->loginSuspended)->toBeFalse()
        ->and($mailboxes[1]->usedBytes)->toBe(1069547520)
        ->and($mailboxes[1]->quotaBytes)->toBeNull()
        ->and($mailboxes[1]->loginSuspended)->toBeTrue()
        ->and($mailboxes[1]->incomingSuspended)->toBeTrue();
});

it('reads databases with a disk size that may arrive as a string', function () {
    Http::fake(['*' => Http::response(usageFixture('list_databases'))]);

    $databases = ServerApiClient::for(usageServerCpanel())->usageOf(usageAccount())->databases;

    expect($databases)->toHaveCount(2)
        ->and($databases[0]->name)->toBe('logi_wp')
        ->and($databases[0]->bytes)->toBe(52428800)
        ->and($databases[1]->bytes)->toBe(1048576);
});

it('asks a cPanel server for the mailboxes of the domain only', function () {
    Http::fake(['*' => Http::response(usageFixture('get_usages'))]);

    ServerApiClient::for(usageServerCpanel())->usageOf(usageAccount('ikinci.com'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cp.example.com:2083/execute/ResourceUsage/get_usages'
        && $request->header('Authorization') === ['cpanel logi:CPTOK']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cp.example.com:2083/execute/Email/list_pops_with_disk?get_restrictions=1&domain=ikinci.com');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cp.example.com:2083/execute/Mysql/list_databases');
});

it('reports a failed UAPI call as an API error', function (Closure $server, array $body) {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => ServerApiClient::for($server())->usageOf(usageAccount()))->toThrow(
        fn (RemoteServerError $error) => expect($error->reason)->toBe(RemoteServerErrorReason::ApiError),
    );
})->with([
    'whm' => [fn () => usageServerWhm(), whmWrapped(['status' => 0, 'errors' => ['nope'], 'data' => null])],
    'cpanel' => [fn () => usageServerCpanel(), ['status' => 0, 'errors' => ['nope']]],
]);

it('rejects a usage answer that is not a list', function (Closure $server, array $body) {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => ServerApiClient::for($server())->usageOf(usageAccount()))->toThrow(
        fn (RemoteServerError $error) => expect($error->reason)->toBe(RemoteServerErrorReason::InvalidResponse),
    );
})->with([
    'whm without data' => [fn () => usageServerWhm(), whmWrapped(['status' => 1, 'data' => null])],
    'whm without a status' => [fn () => usageServerWhm(), ['metadata' => ['result' => 1], 'data' => []]],
    'cpanel with text' => [fn () => usageServerCpanel(), ['status' => 1, 'data' => 'text']],
]);

it('tells an unauthorized token from a failed call when reading usage', function () {
    Http::fake(['*' => Http::response('denied', 401)]);

    expect(fn () => ServerApiClient::for(usageServerCpanel())->usageOf(usageAccount()))->toThrow(
        fn (RemoteServerError $error) => expect($error->reason)->toBe(RemoteServerErrorReason::Unauthorized),
    );
});
