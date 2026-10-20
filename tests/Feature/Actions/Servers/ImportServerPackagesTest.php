<?php

use App\Actions\Servers\ImportServerPackages;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Server;
use App\Support\Servers\RemotePackage;

function remotePackage(string $name = 'gold', string $quota = '5000', string $bw = '10000', string $pop = '20', string $sql = '5', string $addon = '4'): RemotePackage
{
    return new RemotePackage($name, $quota, $bw, $pop, $sql, $addon);
}

it('creates a hosting catalog service with the given price and mapped details', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);

    $result = app(ImportServerPackages::class)->handle($server, [remotePackage()], ['gold' => ['price' => 120000, 'currency' => Currency::Try]]);

    $service = CatalogService::query()->sole();

    expect($result->created)->toBe(['gold'])
        ->and($service->server_id)->toBe($server->id)
        ->and($service->remote_package)->toBe('gold')
        ->and($service->type)->toBe(ServiceType::Hosting)
        ->and($service->name)->toBe('gold')
        ->and($service->price)->toBe(120000)
        ->and($service->details)->toEqual([
            'provider' => 'Ana sunucu',
            'disk_space' => '5000 MB',
            'monthly_traffic' => '10000 MB',
            'email_accounts' => '20',
            'databases' => '5',
            'sites' => '5',
        ]);
});

it('keeps one catalog service over two pulls and leaves name and price alone', function () {
    $server = Server::factory()->whm()->create();
    $action = app(ImportServerPackages::class);
    $action->handle($server, [remotePackage()], ['gold' => ['price' => 120000, 'currency' => Currency::Try]]);

    CatalogService::query()->sole()->update(['name' => 'Altın paket', 'price' => 99900]);

    $result = $action->handle($server, [remotePackage(quota: '8000')], ['gold' => ['price' => 1, 'currency' => Currency::Usd]]);

    $service = CatalogService::query()->sole();

    expect($result->created)->toBe([])
        ->and($result->updated)->toBe(['gold'])
        ->and($service->name)->toBe('Altın paket')
        ->and($service->price)->toBe(99900)
        ->and($service->currency)->toBe(Currency::Try)
        ->and($service->details['disk_space'])->toBe('8000 MB');
});

it('shows Limitsiz for unlimited and zero limits', function (string $value) {
    $server = Server::factory()->whm()->create();

    app(ImportServerPackages::class)->handle(
        $server,
        [remotePackage(quota: $value, bw: $value, pop: $value, sql: $value, addon: $value)],
        ['gold' => ['price' => 100, 'currency' => Currency::Try]],
    );

    expect(CatalogService::query()->sole()->details)->toMatchArray([
        'disk_space' => 'Limitsiz',
        'monthly_traffic' => 'Limitsiz',
        'email_accounts' => 'Limitsiz',
        'databases' => 'Limitsiz',
    ])->and(CatalogService::query()->sole()->details['sites'])->toBe($value === '0' ? '1' : 'Limitsiz');
})->with(['unlimited', '0']);

it('counts the main domain in sites', function () {
    $server = Server::factory()->whm()->create();

    app(ImportServerPackages::class)->handle($server, [remotePackage(addon: '0')], ['gold' => ['price' => 100, 'currency' => Currency::Try]]);

    expect(CatalogService::query()->sole()->details['sites'])->toBe('1');
});

it('skips a new package without a price and reports it', function () {
    $server = Server::factory()->whm()->create();

    $result = app(ImportServerPackages::class)->handle($server, [remotePackage('gold'), remotePackage('mini')], ['mini' => ['price' => 5000, 'currency' => Currency::Usd]]);

    expect($result->skippedWithoutPrice)->toBe(['gold'])
        ->and($result->created)->toBe(['mini'])
        ->and(CatalogService::query()->pluck('remote_package')->all())->toBe(['mini'])
        ->and(CatalogService::query()->sole()->currency)->toBe(Currency::Usd);
});

it('does not mix up the same package name on two servers', function () {
    $first = Server::factory()->whm()->create();
    $second = Server::factory()->whm()->create();
    $prices = ['gold' => ['price' => 100, 'currency' => Currency::Try]];

    app(ImportServerPackages::class)->handle($first, [remotePackage()], $prices);
    $result = app(ImportServerPackages::class)->handle($second, [remotePackage()], $prices);

    expect($result->created)->toBe(['gold'])
        ->and(CatalogService::query()->count())->toBe(2);
});
