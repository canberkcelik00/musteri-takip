<?php

use App\Actions\Servers\RefreshAccountUsage;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Models\Customer;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Models\User;
use App\Support\Servers\RemoteAccount;
use App\Support\Servers\ServerApiClient;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

/**
 * Fakes the four UAPI calls of a WHM usage read; `$domains` is the `list_domains` answer (a
 * fixture name, or null for a refused call).
 */
function fakeAccountCalls(?array $domains): void
{
    $cpanel = fn (string $name): array => json_decode(file_get_contents(base_path("tests/Fixtures/cpanel/{$name}.json")), true);

    Http::fake(function (Request $request) use ($cpanel, $domains) {
        $wrapped = fn (array $uapi) => Http::response(['metadata' => ['result' => 1], 'data' => ['uapi' => $uapi]]);

        return match (true) {
            str_contains($request->url(), 'list_domains') => $domains === null
                ? Http::response(['metadata' => ['result' => 1], 'data' => ['uapi' => ['status' => 0, 'errors' => ['denied'], 'data' => null]]])
                : $wrapped(['status' => 1, 'data' => $domains]),
            str_contains($request->url(), 'get_usages') => $wrapped($cpanel('get_usages')),
            str_contains($request->url(), 'list_pops_with_disk') => $wrapped($cpanel('list_pops_with_disk')),
            default => $wrapped($cpanel('list_databases')),
        };
    });
}

function whmAccountOf(string $user): RemoteAccount
{
    return new RemoteAccount($user, '', $user, null, null, null, false);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('reads the main, addon and parked domains of a WHM account, lower case, without sub domains', function () {
    fakeAccountCalls(['main_domain' => 'Logi.com.tr', 'addon_domains' => ['Ikinci.com'], 'parked_domains' => ['logi.net', 'ikinci.com'], 'sub_domains' => ['blog.logi.com.tr']]);

    $usage = ServerApiClient::for(Server::factory()->whm()->create())->usageOf(whmAccountOf('logi'));

    expect($usage->domains)->toBe(['logi.com.tr', 'ikinci.com', 'logi.net']);
});

it('still reads the usage when the domains cannot be read', function () {
    fakeAccountCalls(null);

    $usage = ServerApiClient::for(Server::factory()->whm()->create())->usageOf(whmAccountOf('logi'));

    expect($usage->domains)->toBeNull()
        ->and($usage->resources)->not->toBeEmpty();
});

it('reads no domains from a cPanel connection', function () {
    Http::fake(fn (Request $request) => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/cpanel/'.match (true) {
        str_contains($request->url(), 'get_usages') => 'get_usages',
        str_contains($request->url(), 'list_pops_with_disk') => 'list_pops_with_disk',
        default => 'list_databases',
    }.'.json')), true)));

    $usage = ServerApiClient::for(Server::factory()->cpanel()->create(['username' => 'logi']))->usageOf(whmAccountOf('logi'));

    expect($usage->domains)->toBeNull();
});

it('stores the domains on the items linked to the account and leaves other accounts alone', function () {
    fakeAccountCalls(['main_domain' => 'logi.com.tr', 'addon_domains' => ['ikinci.com'], 'parked_domains' => []]);
    $server = Server::factory()->whm()->create();
    $linked = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr')->items()->firstOrFail();
    $other = UsageScenario::linkedService($server, 'baska')->items()->firstOrFail();

    app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($linked->fresh()->remote_domains)->toBe(['logi.com.tr', 'ikinci.com'])
        ->and($linked->fresh()->otherDomains())->toBe(['ikinci.com'])
        ->and($other->fresh()->remote_domains)->toBeNull();
});

it('keeps the earlier domains when they cannot be read', function () {
    fakeAccountCalls(null);
    $server = Server::factory()->whm()->create();
    $item = UsageScenario::linkedService($server, 'logi')->items()->firstOrFail();
    $item->forceFill(['remote_domains' => ['logi', 'eski.com']])->save();

    app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($item->fresh()->remote_domains)->toBe(['logi', 'eski.com']);
});

it('leaves out the item\'s own domain, whatever its case', function () {
    $item = new CustomerServiceItem(['details' => ['domain' => 'Logi.com.tr']]);
    $item->remote_domains = ['logi.com.tr', 'ikinci.com'];

    expect($item->otherDomains())->toBe(['ikinci.com'])
        ->and((new CustomerServiceItem)->otherDomains())->toBe([]);
});

it('says on the service row how many other domains the account has', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    $service->items()->firstOrFail()->forceFill(['remote_domains' => ['logi.com.tr', 'ikinci.com', 'logi.net']])->save();

    $html = Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $service->customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable()->getColumn('name')
        ->record($service)->getDescriptionBelow()->toHtml();

    // The domain now carries its icon controls (G-4C.6); the visible text is still the same line.
    expect(trim((string) preg_replace('/\s+/', ' ', strip_tags($html))))->toBe('logi.com.tr +2 alan adı')
        ->and($html)->not->toContain('ikinci.com');
});

it('adds nothing to the service row without other domains', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $service->customer, 'pageClass' => ViewCustomer::class])
        ->assertSee('logi.com.tr')
        ->assertDontSee('alan adı');
});

it('lists the other domains in the edit window and in the usage window', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    $item = $service->items()->firstOrFail();
    $item->forceFill(['remote_domains' => ['logi.com.tr', 'ikinci.com', 'logi.net']])->save();
    UsageScenario::usage($server, 'logi');

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $service->customer, 'pageClass' => ViewCustomer::class])
        ->mountAction(TestAction::make('viewUsage')->arguments(['item' => $item->id]))
        ->assertMountedActionModalSee('Hesaptaki diğer alan adları: ikinci.com, logi.net');
});

it('finds a customer by a domain of its hosting account, in any case', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    $service->items()->firstOrFail()->forceFill(['remote_domains' => ['logi.com.tr', 'ikinci.com']])->save();
    Customer::factory()->create();

    expect(Customer::search('IKINCI.com')->pluck('id')->all())->toBe([$service->customer_id])
        ->and(Customer::search('yok.com')->count())->toBe(0);
});

it('keeps finding a customer by the domain of a cancelled service', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', ['status' => 'cancelled', 'cancelled_on' => now()->toDateString()]);
    $service->items()->firstOrFail()->forceFill(['remote_domains' => ['logi', 'eski-site.com']])->save();

    expect(Customer::search('eski-site')->pluck('id')->all())->toBe([$service->customer_id]);
});
