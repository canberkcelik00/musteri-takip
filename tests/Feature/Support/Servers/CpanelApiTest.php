<?php

use App\Enums\RemoteServerErrorReason;
use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Support\Servers\CpanelApi;
use App\Support\Servers\RemoteAccount;
use App\Support\Servers\ServerApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function cpanelServer(): Server
{
    return Server::factory()->cpanel()->create(['hostname' => 'cp.example.com', 'username' => 'logi', 'api_token' => 'CPTOK']);
}

it('builds a cPanel client for a cPanel server', function () {
    expect(ServerApiClient::for(cpanelServer()))->toBeInstanceOf(CpanelApi::class);
});

it('turns the main and addon domains into accounts and has no packages', function () {
    Http::fake(['*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/cpanel/list_domains.json')), true))]);
    $api = ServerApiClient::for(cpanelServer());

    $accounts = $api->accounts();

    expect($accounts)->toHaveCount(3)
        ->and(array_map(fn ($account) => $account->key, $accounts))->toBe(['logi.com.tr', 'ikinci.com', 'ucuncu.net'])
        ->and($accounts[1]->domain)->toBe('ikinci.com')
        ->and($accounts[1]->username)->toBe('logi')
        ->and($accounts[1]->suspended)->toBeFalse()
        ->and($accounts[1]->startDate)->toBeNull()
        ->and($api->packages())->toBe([]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cp.example.com:2083/execute/DomainInfo/list_domains'
        && $request->header('Authorization') === ['cpanel logi:CPTOK']);
});

it('reports a UAPI status 0 as an API error', function () {
    Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['nope']])]);

    expect(fn () => ServerApiClient::for(cpanelServer())->accounts())->toThrow(
        fn (RemoteServerError $error) => expect($error->reason)->toBe(RemoteServerErrorReason::ApiError),
    );
});

it('has only the domain itself for a cPanel account and asks nothing', function () {
    Http::fake();

    $domains = ServerApiClient::for(cpanelServer())->domainsOf(new RemoteAccount('ikinci.com', 'ikinci.com', 'logi', null, null, null, false));

    expect($domains)->toBe(['ikinci.com']);

    Http::assertNothingSent();
});
