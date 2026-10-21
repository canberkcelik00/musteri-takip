<?php

use App\Enums\RemoteServerErrorReason;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function openServerUsage(Server $server): TestAction
{
    return TestAction::make('viewServerUsage')->table($server);
}

it('opens the server window from the usage column and shows the totals and every account', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu', 'hostname' => 'sunucu.example.com']);
    ServerUsage::factory()->create(['server_id' => $server->id]);
    $service = UsageScenario::linkedService($server, 'logi');

    Livewire::test(ManageServers::class)
        ->mountAction(openServerUsage($server))
        ->assertMountedActionModalSee([
            'Ana sunucu', 'sunucu.example.com',
            'Bayi diski', '38 GB / 50 GB', 'Bayi trafiği', '120 GB · Limitsiz', 'Bayi hesap sayısı', '24 / 30',
            'Aktif hesap', 'Askıdaki hesap', 'Müşteriye bağlı olmayan hesap',
            'Hesaplara tahsis edilen disk', '+ 1 limitsiz hesap',
            'Hesaplar (2)', 'logi.com.tr', 'gold', $service->customer->short_name,
            'askida.com', 'Askıda', 'Bağlı değil', 'Dolmak üzere',
        ])
        ->assertMountedActionModalDontSee('okunamadı')
        ->assertMountedActionModalSeeHtml(CustomerResource::getUrl('view', ['record' => $service->customer_id]));

});

it('says so when the reseller limits could not be read', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id, 'resources' => [
        ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => 40802189312, 'limit' => null, 'is_bytes' => true, 'limit_read' => false],
    ]]);

    Livewire::test(ManageServers::class)
        ->mountAction(openServerUsage($server))
        ->assertMountedActionModalSee(['Bayi limitleri sunucudan okunamadı', '38 GB']);
});

it('says what to do when nothing was read yet', function () {
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->mountAction(openServerUsage($server))
        ->assertMountedActionModalSee('Henüz bu sunucunun kullanımı okunmadı');
});

it('shows the failure of the last read together with the old values', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->failed(RemoteServerErrorReason::Unauthorized)->create(['server_id' => $server->id]);

    Livewire::test(ManageServers::class)
        ->mountAction(openServerUsage($server))
        ->assertMountedActionModalSee(['Son yenileme başarısız: yetki hatası', 'Gösterilen veriler', 'logi.com.tr']);
});

it('reads the server again with "Şimdi yenile"', function () {
    $server = Server::factory()->whm()->create();
    Http::fake(function ($request) {
        $call = match (true) {
            str_contains($request->url(), 'resellerstats') => 'resellerstats',
            str_contains($request->url(), 'acctcounts') => 'acctcounts',
            str_contains($request->url(), 'showbw') => 'showbw',
            default => 'listaccts',
        };

        return Http::response(json_decode(file_get_contents(base_path("tests/Fixtures/whm/{$call}.json")), true));
    });

    Livewire::test(ManageServers::class)
        ->callAction([openServerUsage($server), TestAction::make('refreshServerUsage')])
        ->assertNotified('Kullanım yenilendi.');

    expect(ServerUsage::query()->firstOrFail()->accounts)->toHaveCount(2);
});

it('reports a failed "Şimdi yenile" and keeps the earlier values', function () {
    $server = Server::factory()->whm()->create();
    $earlier = ServerUsage::factory()->create(['server_id' => $server->id]);
    Http::fake(['*' => Http::response('denied', 401)]);

    Livewire::test(ManageServers::class)
        ->callAction([openServerUsage($server), TestAction::make('refreshServerUsage')])
        ->assertNotified('Son yenileme başarısız: yetki hatası.');

    expect(ServerUsage::query()->firstOrFail()->accounts)->toEqual($earlier->accounts);
});

it('does not open a window for a cPanel server', function () {
    $server = Server::factory()->cpanel()->create();

    Livewire::test(ManageServers::class)
        ->assertActionHidden(openServerUsage($server));
});
