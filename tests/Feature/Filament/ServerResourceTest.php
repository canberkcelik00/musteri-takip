<?php

use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Filament\Resources\Servers\ServerResource;
use App\Models\CatalogService;
use App\Models\Server;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function editServerPanel(Server $server): TestAction
{
    return TestAction::make('edit')->table($server);
}

it('lists the servers under the settings group', function () {
    $server = Server::factory()->create(['name' => 'Ana WHM']);

    Livewire::test(ManageServers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$server])
        ->assertSee('Ana WHM');

    expect(ServerResource::getNavigationGroup())->toBe('Ayarlar');
});

it('creates a WHM server with the default port and an encrypted token', function () {
    Livewire::test(ManageServers::class)
        ->callAction('create', [
            'type' => ServerType::Whm->value,
            'name' => 'Ana WHM',
            'hostname' => 'whm.example.com',
            'port' => 2087,
            'username' => 'reseller',
            'api_token' => 'TOKEN-VALUE',
        ])
        ->assertHasNoFormErrors();

    $server = Server::query()->where('name', 'Ana WHM')->firstOrFail();

    expect($server->type)->toBe(ServerType::Whm)
        ->and($server->api_token)->toBe('TOKEN-VALUE')
        ->and($server->catalog_service_id)->toBeNull();
});

it('requires the hosting catalog service for a cPanel server', function () {
    Livewire::test(ManageServers::class)
        ->callAction('create', [
            'type' => ServerType::Cpanel->value,
            'name' => 'cPanel',
            'hostname' => 'cp.example.com',
            'port' => 2083,
            'username' => 'logi',
            'api_token' => 'TOKEN',
            'catalog_service_id' => null,
        ])
        ->assertHasFormErrors(['catalog_service_id' => 'required']);

    expect(Server::query()->count())->toBe(0);

    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();

    Livewire::test(ManageServers::class)
        ->callAction('create', [
            'type' => ServerType::Cpanel->value,
            'name' => 'cPanel',
            'hostname' => 'cp.example.com',
            'port' => 2083,
            'username' => 'logi',
            'api_token' => 'TOKEN',
            'catalog_service_id' => $hosting->id,
        ])
        ->assertHasNoFormErrors();

    expect(Server::query()->firstOrFail()->catalog_service_id)->toBe($hosting->id);
});

it('rejects a catalog service that is not an active hosting service', function (string $kind) {
    $service = match ($kind) {
        'domain' => CatalogService::factory()->ofType(ServiceType::Domain)->create(),
        'archived hosting' => CatalogService::factory()->ofType(ServiceType::Hosting)->archived()->create(),
    };

    Livewire::test(ManageServers::class)
        ->callAction('create', [
            'type' => ServerType::Cpanel->value,
            'name' => 'cPanel',
            'hostname' => 'cp.example.com',
            'port' => 2083,
            'username' => 'logi',
            'api_token' => 'TOKEN',
            'catalog_service_id' => $service->id,
        ])
        ->assertHasFormErrors(['catalog_service_id']);

    expect(Server::query()->count())->toBe(0);
})->with(['domain', 'archived hosting']);

it('requires the token on create and rejects a duplicate name or a bad port', function () {
    Server::factory()->create(['name' => 'Ana WHM']);

    Livewire::test(ManageServers::class)
        ->callAction('create', [
            'type' => ServerType::Whm->value,
            'name' => 'Ana WHM',
            'hostname' => 'whm.example.com',
            'port' => 70000,
            'username' => 'reseller',
            'api_token' => '',
        ])
        ->assertHasFormErrors(['name' => 'unique', 'port' => 'max', 'api_token' => 'required']);
});

it('keeps the old token when the token field is left empty on edit', function () {
    $server = Server::factory()->create(['api_token' => 'OLD-TOKEN']);

    Livewire::test(ManageServers::class)
        ->callAction(editServerPanel($server), ['name' => 'Yeni ad', 'api_token' => ''])
        ->assertHasNoFormErrors();

    expect($server->refresh()->name)->toBe('Yeni ad')
        ->and($server->api_token)->toBe('OLD-TOKEN');
});

it('replaces the token when a new one is given on edit', function () {
    $server = Server::factory()->create(['api_token' => 'OLD-TOKEN']);

    Livewire::test(ManageServers::class)
        ->callAction(editServerPanel($server), ['api_token' => 'NEW-TOKEN'])
        ->assertHasNoFormErrors();

    expect($server->refresh()->api_token)->toBe('NEW-TOKEN');
});

it('never shows the stored token in the edit form', function () {
    $server = Server::factory()->create(['api_token' => 'OLD-TOKEN']);

    Livewire::test(ManageServers::class)
        ->mountAction(editServerPanel($server))
        ->assertSchemaStateSet(['api_token' => null])
        ->assertDontSee('OLD-TOKEN');
});

it('clears the catalog service when a cPanel server becomes WHM', function () {
    $server = Server::factory()->cpanel()->create();

    Livewire::test(ManageServers::class)
        ->callAction(editServerPanel($server), ['type' => ServerType::Whm->value])
        ->assertHasNoFormErrors();

    expect($server->refresh()->type)->toBe(ServerType::Whm)
        ->and($server->catalog_service_id)->toBeNull();
});

it('tests the connection from the row and tells the result', function () {
    Http::fake(['*' => Http::response(['metadata' => ['result' => 1]])]);
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction([editServerPanel($server), TestAction::make('testConnection')])
        ->assertNotified('Bağlantı başarılı.');
});

it('tells why the connection failed', function () {
    Http::fake(['*' => Http::response('no', 401)]);
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->callAction([editServerPanel($server), TestAction::make('testConnection')])
        ->assertNotified(__('servers.connection_unauthorized'));
});

it('hides archived servers by default and shows them with the archive filter', function () {
    $active = Server::factory()->create();
    $archived = Server::factory()->archived()->create();

    Livewire::test(ManageServers::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived])
        ->filterTable('archived', true)
        ->assertCanSeeTableRecords([$archived]);
});

it('archives and unarchives a server', function () {
    $server = Server::factory()->create();

    Livewire::test(ManageServers::class)
        ->callAction(sectionAction(editServerPanel($server), 'archive'))
        ->assertNotified(__('archive.archived'));

    expect($server->refresh()->isArchived())->toBeTrue();

    Livewire::test(ManageServers::class)
        ->filterTable('archived', true)
        ->callAction(sectionAction(editServerPanel($server), 'unarchive'));

    expect($server->refresh()->isArchived())->toBeFalse();
});

it('deletes an unused server but only offers archive for one with pulled packages', function () {
    $unused = Server::factory()->create();
    $used = Server::factory()->create();
    CatalogService::factory()->create(['server_id' => $used->id, 'remote_package' => 'gold']);

    expect(actionOffered(Livewire::test(ManageServers::class), sectionAction(editServerPanel($unused), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered(Livewire::test(ManageServers::class), sectionAction(editServerPanel($used), 'delete', danger: true)))->toBeFalse()
        ->and(actionOffered(Livewire::test(ManageServers::class), sectionAction(editServerPanel($used), 'archive')))->toBeTrue();

    Livewire::test(ManageServers::class)
        ->callAction(sectionAction(editServerPanel($unused), 'delete', danger: true));

    expect(Server::query()->find($unused->id))->toBeNull()
        ->and(Server::query()->find($used->id))->not->toBeNull();
});
