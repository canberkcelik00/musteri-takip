<?php

use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Livewire\Pages\Settings\Servers;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditServer;
use App\Livewire\Windows\PullFromServer;
use App\Livewire\Windows\ViewServerUsage;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

/**
 * Fakes the WHM answers the pull window reads.
 */
function uiFakeWhm(): void
{
    Http::fake([
        '*/json-api/listpkgs*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listpkgs.json')), 200, ['Content-Type' => 'application/json']),
        '*/json-api/listaccts*' => Http::response(file_get_contents(base_path('tests/Fixtures/whm/listaccts.json')), 200, ['Content-Type' => 'application/json']),
    ]);
}

it('lists servers and hides archived ones', function () {
    Server::factory()->whm()->create(['name' => 'Ana WHM']);
    Server::factory()->whm()->archived()->create(['name' => 'Eski WHM']);

    Livewire::test(Servers::class)
        ->assertSee('Ana WHM')->assertDontSee('Eski WHM')
        ->set('archive', Servers::ARCHIVE_ARCHIVED)->assertSee('Eski WHM')->assertDontSee('Ana WHM');
});

it('creates a WHM server and fills in the usual port of the chosen type', function () {
    Livewire::test(EditServer::class)
        ->assertSet('port', (string) ServerType::Whm->defaultPort())
        ->set('type', ServerType::Cpanel->value)
        ->assertSet('port', (string) ServerType::Cpanel->defaultPort())
        ->set('type', ServerType::Whm->value)
        ->set('name', 'Yeni WHM')
        ->set('hostname', 'whm.example.com')
        ->set('username', 'reseller')
        ->set('api_token', 'SECRET')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('servers-changed')
        ->assertDispatched('close-window');

    expect(Server::query()->where('name', 'Yeni WHM')->firstOrFail()->api_token)->toBe('SECRET');
});

it('requires a token for a new server but keeps the old token when the field is left blank', function () {
    Livewire::test(EditServer::class)
        ->set('name', 'Yeni WHM')->set('hostname', 'whm.example.com')->set('username', 'reseller')
        ->call('save')
        ->assertHasErrors('api_token');

    $server = Server::factory()->whm()->create(['api_token' => 'OLD-TOKEN']);

    Livewire::test(EditServer::class, ['server' => $server->id])
        ->assertSet('api_token', '')
        ->set('name', 'Yeniden Adlandı')
        ->call('save')
        ->assertHasNoErrors();

    expect($server->fresh()->name)->toBe('Yeniden Adlandı')->and($server->fresh()->api_token)->toBe('OLD-TOKEN');

    Livewire::test(EditServer::class, ['server' => $server->id])->set('api_token', 'NEW-TOKEN')->call('save');

    expect($server->fresh()->api_token)->toBe('NEW-TOKEN');
});

it('needs an unarchived hosting catalog service for a cPanel server', function () {
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();
    $domain = CatalogService::factory()->ofType(ServiceType::Domain)->create();
    $archived = CatalogService::factory()->ofType(ServiceType::Hosting)->archived()->create();

    $form = Livewire::test(EditServer::class)
        ->set('type', ServerType::Cpanel->value)
        ->set('name', 'cPanel')->set('hostname', 'cp.example.com')->set('username', 'logi')->set('api_token', 'TOK');

    $form->call('save')->assertHasErrors('catalog_service_id');
    $form->set('catalog_service_id', (string) $domain->id)->call('save')->assertHasErrors('catalog_service_id');
    $form->set('catalog_service_id', (string) $archived->id)->call('save')->assertHasErrors('catalog_service_id');
    $form->set('catalog_service_id', (string) $hosting->id)->call('save')->assertHasNoErrors();

    expect(Server::query()->where('name', 'cPanel')->firstOrFail()->catalog_service_id)->toBe($hosting->id);
});

it('tests the connection of a saved server and shows the result as a toast', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['metadata' => ['result' => 1], 'data' => ['version' => '11.120.0']])
        ->push('Access denied', 403)]);
    $server = Server::factory()->whm()->create();

    Livewire::test(EditServer::class, ['server' => $server->id])
        ->call('testConnection')
        ->assertDispatched('notify', message: 'Bağlantı başarılı.', tone: 'success')
        ->call('testConnection')
        ->assertDispatched('notify', tone: 'danger');
});

it('archives, restores and deletes a server through the confirm window', function () {
    $server = Server::factory()->whm()->create();

    Livewire::test(Confirm::class, ['action' => 'archive-server', 'id' => $server->id])->call('confirm');
    expect($server->fresh()->isArchived())->toBeTrue();

    Livewire::test(Confirm::class, ['action' => 'unarchive-server', 'id' => $server->id])->call('confirm');
    expect($server->fresh()->isArchived())->toBeFalse();

    Livewire::test(Confirm::class, ['action' => 'delete-server', 'id' => $server->id])->call('confirm');
    expect(Server::query()->find($server->id))->toBeNull();
});

it('shows the usage window of a WHM server', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana WHM']);

    Livewire::test(ViewServerUsage::class, ['server' => $server->id])
        ->assertSee('Ana WHM')
        ->assertSee(__('usage.never_read'));
});

it('shows what a pull would do and imports the accounts after the prices are given', function () {
    uiFakeWhm();
    $server = Server::factory()->whm()->create();

    $window = Livewire::test(PullFromServer::class, ['server' => $server->id])
        ->assertSet('error', null)
        ->assertSee(__('servers.pull_new_packages'));

    $window->call('importAccounts')->assertHasErrors(['prices.0', 'prices.1']);

    $window
        ->set('prices.0', '1.000')->set('prices.1', '500')
        ->call('importAccounts')
        ->assertHasNoErrors()
        ->assertSet('done', true)
        ->assertDispatched('servers-changed')
        ->assertDispatched('customers-changed')
        ->assertDispatched('close-window');

    expect(Customer::query()->pluck('short_name')->all())->toBe(['logi.com.tr']);
});

it('leaves an account the user took off the list out of the import', function () {
    uiFakeWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(PullFromServer::class, ['server' => $server->id])
        ->call('removeRow', 0)
        ->set('prices.0', '1.000')->set('prices.1', '500')
        ->call('importAccounts')
        ->assertHasNoErrors();

    expect(Customer::query()->count())->toBe(0);
});

it('asks for a custom customer name when "Diğer" is chosen', function () {
    uiFakeWhm();
    $server = Server::factory()->whm()->create();

    Livewire::test(PullFromServer::class, ['server' => $server->id])
        ->set('prices.0', '1.000')->set('prices.1', '500')
        ->set('choices.0', PullFromServer::OTHER)
        ->call('importAccounts')
        ->assertHasErrors('customs.0')
        ->set('customs.0', 'Logi Bilişim')
        ->call('importAccounts')
        ->assertHasNoErrors();

    expect(Customer::query()->pluck('short_name')->all())->toBe(['Logi Bilişim']);
});

it('shows the server error instead of a form when the pull cannot read the server', function () {
    Http::fake(['*' => Http::response('Access denied', 403)]);
    $server = Server::factory()->whm()->create();

    Livewire::test(PullFromServer::class, ['server' => $server->id])
        ->assertNotSet('error', null)
        ->call('importAccounts')
        ->assertNotDispatched('servers-changed');
});

it('needs a signed-in user for the servers page', function () {
    auth()->logout();

    $this->get(route('app.servers'))->assertRedirect();
});
