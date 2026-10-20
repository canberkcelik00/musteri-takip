<?php

use App\Actions\Servers\TestServerConnection;
use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('succeeds when a WHM server accepts the token and sends the whm header', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1], 'data' => ['version' => '11.120.0']])]);
    $server = Server::factory()->whm()->create(['hostname' => 'sunucu.example.com', 'username' => 'reseller', 'api_token' => 'TOK123']);

    $result = app(TestServerConnection::class)->handle($server);

    expect($result->successful)->toBeTrue()
        ->and($result->message)->toBe('Bağlantı başarılı.');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sunucu.example.com:2087/json-api/version?api.version=1'
        && $request->header('Authorization') === ['whm reseller:TOK123']);
});

it('succeeds when a cPanel server accepts the token and sends the cpanel header', function () {
    Http::fake(['*' => Http::response(['status' => 1, 'data' => ['user' => 'logi']])]);
    $server = Server::factory()->cpanel()->create(['hostname' => 'cp.example.com', 'username' => 'logi', 'api_token' => 'CPTOK']);

    $result = app(TestServerConnection::class)->handle($server);

    expect($result->successful)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cp.example.com:2083/execute/Variables/get_user_information'
        && $request->header('Authorization') === ['cpanel logi:CPTOK']);
});

it('reports an authorization failure for HTTP 401 and 403', function (int $status) {
    Http::fake(['*' => Http::response('Access denied', $status)]);

    $result = app(TestServerConnection::class)->handle(Server::factory()->create());

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('kabul etmedi');
})->with([401, 403]);

it('reports a timeout', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds'));

    $result = app(TestServerConnection::class)->handle(Server::factory()->create());

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('zamanında yanıt vermedi');
});

it('reports a connection failure', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

    $result = app(TestServerConnection::class)->handle(Server::factory()->create());

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('bağlanılamadı');
});

it('reports an unexpected response when the body does not confirm success', function (array $body) {
    Http::fake(['*' => Http::response($body)]);

    $result = app(TestServerConnection::class)->handle(Server::factory()->whm()->create());

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('beklenmeyen');
})->with([
    'result 0' => [['metadata' => ['result' => 0, 'reason' => 'x']]],
    'not json' => [['foo' => 'bar']],
]);

it('never puts the token in the message', function () {
    Http::fake(['*' => Http::response('bad TOPSECRET', 500)]);
    $server = Server::factory()->create(['api_token' => 'TOPSECRET']);

    $result = app(TestServerConnection::class)->handle($server);

    expect($result->message)->not->toContain('TOPSECRET');
});
