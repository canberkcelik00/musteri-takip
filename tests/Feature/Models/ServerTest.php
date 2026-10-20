<?php

use App\Enums\ServerType;
use App\Models\CatalogService;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores the api token encrypted', function () {
    $server = Server::factory()->create(['api_token' => 'SECRET-TOKEN-123']);

    $raw = DB::table('servers')->where('id', $server->id)->value('api_token');

    expect($raw)->not->toContain('SECRET-TOKEN-123')
        ->and($server->refresh()->api_token)->toBe('SECRET-TOKEN-123');
});

it('hides the api token from serialization', function () {
    $server = Server::factory()->create(['api_token' => 'SECRET-TOKEN-123']);

    expect($server->toArray())->not->toHaveKey('api_token')
        ->and($server->toJson())->not->toContain('SECRET-TOKEN-123');
});

it('casts the type and gives each type its default port', function () {
    expect(Server::factory()->whm()->create()->type)->toBe(ServerType::Whm)
        ->and(Server::factory()->cpanel()->create()->type)->toBe(ServerType::Cpanel)
        ->and(ServerType::Whm->defaultPort())->toBe(2087)
        ->and(ServerType::Cpanel->defaultPort())->toBe(2083);
});

it('gives a cpanel server a hosting catalog service', function () {
    $server = Server::factory()->cpanel()->create();

    expect($server->catalogService)->toBeInstanceOf(CatalogService::class)
        ->and(Server::factory()->whm()->create()->catalogService)->toBeNull();
});

it('requires a unique name', function () {
    Server::factory()->create(['name' => 'Ana sunucu']);

    expect(fn () => Server::factory()->create(['name' => 'Ana sunucu']))
        ->toThrow(QueryException::class);
});

it('is in use once a catalog service or an item was pulled from it', function () {
    $server = Server::factory()->create();

    expect($server->isInUse())->toBeFalse();

    CatalogService::factory()->create(['server_id' => $server->id, 'remote_package' => 'gold']);

    expect($server->refresh()->isInUse())->toBeTrue();

    $other = Server::factory()->create();
    CustomerServiceItem::factory()->create(['server_id' => $other->id, 'remote_key' => 'logi']);

    expect($other->isInUse())->toBeTrue();
});

it('does not allow the same package or account twice on one server', function () {
    $server = Server::factory()->create();
    CatalogService::factory()->create(['server_id' => $server->id, 'remote_package' => 'gold']);
    CustomerServiceItem::factory()->create(['server_id' => $server->id, 'remote_key' => 'logi']);

    expect(fn () => CatalogService::factory()->create(['server_id' => $server->id, 'remote_package' => 'gold']))
        ->toThrow(QueryException::class)
        ->and(fn () => CustomerServiceItem::factory()->create(['server_id' => $server->id, 'remote_key' => 'logi']))
        ->toThrow(QueryException::class);
});

it('allows many unlinked catalog services and items', function () {
    CatalogService::factory()->count(2)->create();
    CustomerServiceItem::factory()->count(2)->create();

    expect(CatalogService::query()->whereNull('server_id')->count())->toBe(CatalogService::query()->count())
        ->and(CustomerServiceItem::query()->whereNull('server_id')->count())->toBe(CustomerServiceItem::query()->count())
        ->and(CustomerServiceItem::query()->count())->toBeGreaterThanOrEqual(2);
});
