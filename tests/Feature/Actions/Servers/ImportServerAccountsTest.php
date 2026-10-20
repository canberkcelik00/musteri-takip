<?php

use App\Actions\Servers\ImportServerAccounts;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Support\Servers\RemoteAccount;
use Carbon\CarbonImmutable;

function serverAccount(string $domain = 'ornek.com', ?string $username = 'ornek', ?string $package = 'gold', ?string $start = '2024-03-15', bool $suspended = false, ?string $email = 'info@ornek.com', ?string $key = null): RemoteAccount
{
    return new RemoteAccount(
        $key ?? $username ?? $domain,
        $domain,
        $username ?? '',
        $email,
        $package,
        $start === null ? null : CarbonImmutable::parse($start),
        $suspended,
    );
}

function hostingCatalogFor(Server $server, string $package = 'gold'): CatalogService
{
    return CatalogService::factory()->ofType(ServiceType::Hosting)->create([
        'server_id' => $server->id,
        'remote_package' => $package,
        'name' => $package,
        'price' => 120000,
    ]);
}

function manualHostingItem(array $details, bool $cancelled = false): CustomerServiceItem
{
    $catalog = CatalogService::factory()->ofType(ServiceType::Hosting)->create();
    $service = CustomerService::factory()->forCatalogService($catalog);
    $service = ($cancelled ? $service->cancelled() : $service)->create();
    $item = $service->items()->sole();
    $item->update(['details' => $details]);

    return $item;
}

beforeEach(fn () => $this->travelTo('2026-10-20 10:00:00'));

it('creates a customer, contact and service for a WHM account without any charge', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);
    $catalog = hostingCatalogFor($server);

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount()]);

    $customer = Customer::query()->sole();
    $service = CustomerService::query()->sole();
    $item = CustomerServiceItem::query()->sole();

    expect($result->created)->toBe(['ornek'])
        ->and($result->customersCreated)->toBe(1)
        ->and($customer->short_name)->toBe('ornek.com')
        ->and($customer->primaryContact->name)->toBe('ornek.com')
        ->and($customer->primaryContact->email)->toBe('info@ornek.com')
        ->and($service->catalog_service_id)->toBe($catalog->id)
        ->and($service->start_date->toDateString())->toBe('2026-03-15')
        ->and($service->end_date->toDateString())->toBe('2027-03-15')
        ->and($item->server_id)->toBe($server->id)
        ->and($item->remote_key)->toBe('ornek')
        ->and($item->details)->toEqual(['domain' => 'ornek.com', 'server' => 'Ana sunucu', 'cpanel_username' => 'ornek'])
        ->and(Charge::query()->count())->toBe(0)
        ->and($server->fresh()->last_pulled_at)->not->toBeNull();
});

it('creates nothing on a second pull', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);
    $action = app(ImportServerAccounts::class);

    $action->handle($server, [serverAccount()]);
    $result = $action->handle($server, [serverAccount()]);

    expect($result->alreadyLinked)->toBe(['ornek'])
        ->and($result->created)->toBe([])
        ->and($result->customersCreated)->toBe(0)
        ->and(Customer::query()->count())->toBe(1)
        ->and(CustomerService::query()->count())->toBe(1)
        ->and(CustomerServiceItem::query()->count())->toBe(1);
});

it('links a manual hosting item with the same domain instead of duplicating it', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);
    hostingCatalogFor($server);
    $item = manualHostingItem(['domain' => 'ORNEK.com', 'server' => 'Eski sunucu']);
    $before = [Customer::query()->count(), CustomerService::query()->count()];

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount()]);

    $item->refresh();

    expect($result->linked)->toBe(['ornek'])
        ->and($result->created)->toBe([])
        ->and([Customer::query()->count(), CustomerService::query()->count()])->toBe($before)
        ->and($item->server_id)->toBe($server->id)
        ->and($item->remote_key)->toBe('ornek')
        ->and($item->details)->toEqual(['domain' => 'ORNEK.com', 'server' => 'Eski sunucu', 'cpanel_username' => 'ornek']);
});

it('links a manual hosting item by cPanel username on WHM', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);
    $item = manualHostingItem(['domain' => 'baska.com', 'cpanel_username' => 'ornek']);

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount()]);

    expect($result->linked)->toBe(['ornek'])
        ->and($item->refresh()->remote_key)->toBe('ornek');
});

it('does not link by cPanel username on a cPanel server, where one user owns every domain', function () {
    $server = Server::factory()->cpanel()->create();
    manualHostingItem(['domain' => 'baska.com', 'cpanel_username' => 'tekuser']);

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount('ornek.com', 'tekuser', null, null, key: 'ornek.com')], CarbonImmutable::parse('2026-01-01'));

    expect($result->linked)->toBe([])
        ->and($result->created)->toBe(['ornek.com']);
});

it('does not link a cancelled service or an item of another server', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);
    $cancelled = manualHostingItem(['domain' => 'ornek.com'], cancelled: true);
    $other = manualHostingItem(['domain' => 'ornek.com']);
    $other->forceFill(['server_id' => Server::factory()->whm()->create()->id, 'remote_key' => 'ornek'])->save();

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount()]);

    expect($result->created)->toBe(['ornek'])
        ->and($cancelled->refresh()->server_id)->toBeNull();
});

it('skips suspended accounts and counts them', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount(suspended: true)]);

    expect($result->suspendedSkipped)->toBe(['ornek'])
        ->and($result->created)->toBe([])
        ->and(Customer::query()->count())->toBe(0);
});

it('puts each cPanel domain in its own customer with the server hosting service and the given start date', function () {
    $server = Server::factory()->cpanel()->create();

    $result = app(ImportServerAccounts::class)->handle($server, [
        serverAccount('bir.com', 'tekuser', null, null, email: null, key: 'bir.com'),
        serverAccount('iki.com', 'tekuser', null, null, email: null, key: 'iki.com'),
    ], CarbonImmutable::parse('2025-12-01'));

    expect($result->created)->toBe(['bir.com', 'iki.com'])
        ->and($result->customersCreated)->toBe(2)
        ->and(Customer::query()->orderBy('id')->pluck('short_name')->all())->toBe(['bir.com', 'iki.com'])
        ->and(CustomerService::query()->pluck('catalog_service_id')->unique()->all())->toBe([$server->catalog_service_id])
        ->and(CustomerService::query()->pluck('start_date')->map->toDateString()->unique()->all())->toBe(['2025-12-01'])
        ->and(Customer::query()->first()->primaryContact->email)->toBeNull();
});

it('moves an old cPanel start date to the anniversary covering today', function () {
    $server = Server::factory()->cpanel()->create();

    app(ImportServerAccounts::class)->handle($server, [serverAccount('bir.com', 'tekuser', null, null, key: 'bir.com')], CarbonImmutable::parse('2023-05-10'));

    expect(CustomerService::query()->sole()->start_date->toDateString())->toBe('2026-05-10');
});

it('reports an account whose package has no catalog service and still imports the others', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);

    $result = app(ImportServerAccounts::class)->handle($server, [
        serverAccount('yok.com', 'yok', 'bilinmeyen'),
        serverAccount('var.com', 'var', 'gold'),
    ]);

    expect($result->errors)->toHaveKey('yok')
        ->and($result->errors['yok'])->toContain('bilinmeyen')
        ->and($result->created)->toBe(['var'])
        ->and(Customer::query()->pluck('short_name')->all())->toBe(['var.com']);
});

it('reports a cPanel server without a hosting service', function () {
    $server = Server::factory()->cpanel()->create();
    $server->forceFill(['catalog_service_id' => null])->save();

    $result = app(ImportServerAccounts::class)->handle($server->fresh(), [serverAccount('bir.com', 'tekuser', null, null, key: 'bir.com')], CarbonImmutable::parse('2026-01-01'));

    expect($result->errors)->toHaveKey('bir.com')
        ->and(Customer::query()->count())->toBe(0);
});

it('rolls back the new customer when the service cannot be assigned', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server)->forceFill(['price' => 0])->save();

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount()]);

    expect($result->errors)->toHaveKey('ornek')
        ->and(Customer::query()->count())->toBe(0)
        ->and(CustomerService::query()->count())->toBe(0);
});

it('adds the service to an existing customer with the same short name', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);
    $customer = Customer::factory()->create(['short_name' => 'ornek.com']);

    $result = app(ImportServerAccounts::class)->handle($server, [serverAccount()]);

    expect($result->created)->toBe(['ornek'])
        ->and($result->customersCreated)->toBe(0)
        ->and(Customer::query()->count())->toBe(1)
        ->and($customer->customerServices()->count())->toBe(1);
});

it('reports linked items the server no longer lists without touching them', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);
    $action = app(ImportServerAccounts::class);
    $action->handle($server, [serverAccount(), serverAccount('diger.com', 'diger')]);

    $result = $action->handle($server, [serverAccount()]);

    expect($result->missingOnServer)->toBe(['diger'])
        ->and(CustomerService::query()->where('status', CustomerServiceStatus::Active)->count())->toBe(2)
        ->and(CustomerServiceItem::query()->count())->toBe(2);
});

it('lets the next renewal charge come from the normal rule', function () {
    $server = Server::factory()->whm()->create();
    hostingCatalogFor($server);

    app(ImportServerAccounts::class)->handle($server, [serverAccount()]);
    $this->travelTo('2027-02-13 10:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    $charge = Charge::query()->sole();

    expect($charge->is_renewal)->toBeTrue()
        ->and($charge->period_start->toDateString())->toBe('2027-03-15');
});
