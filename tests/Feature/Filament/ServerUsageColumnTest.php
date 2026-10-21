<?php

use App\Enums\RemoteServerErrorReason;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('says on the servers list when the usage was read and how many warnings there are', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', null, ['refreshed_at' => '2026-10-21 14:30:00']);

    Livewire::test(ManageServers::class)
        ->assertTableColumnStateSet('usage', '21.10.2026 14:30 · 1 uyarı', $server);
});

it('shows a failed refresh in the column', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::usage($server, 'logi', null, ['failed_at' => now(), 'failure_reason' => RemoteServerErrorReason::Unauthorized]);

    Livewire::test(ManageServers::class)
        ->assertTableColumnStateSet('usage', 'Yenilenemedi: yetki hatası', $server);
});

it('says so when nothing was read yet', function () {
    $server = Server::factory()->whm()->create();

    Livewire::test(ManageServers::class)
        ->assertTableColumnStateSet('usage', 'Henüz okunmadı', $server);
});

it('shows the reseller totals with their limits under the usage', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id]);

    Livewire::test(ManageServers::class)
        ->assertTableColumnHasDescription('usage', 'Bayi: Disk 38 GB / 50 GB · Trafik 120 GB / sınırsız · 24 / 30 hesap', $server)
        ->assertTableColumnDoesNotHaveDescription('usage', 'Bayi: Disk 38 GB', $server);
});

it('shows only the totals when the limits could not be read', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id, 'resources' => [
        ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => 40802189312, 'limit' => null, 'is_bytes' => true, 'limit_read' => false],
        ['id' => 'reseller_accounts', 'label' => 'Reseller Accounts', 'used' => 24, 'limit' => null, 'is_bytes' => false, 'limit_read' => false],
    ]]);

    Livewire::test(ManageServers::class)
        ->assertTableColumnHasDescription('usage', 'Bayi: Disk 38 GB · 24 hesap', $server);
});

it('counts the reseller warning and shows a failed totals refresh', function () {
    $nearlyFull = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $nearlyFull->id, 'refreshed_at' => '2026-10-21 14:30:00', 'resources' => [
        ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => 48 * 1073741824, 'limit' => 50 * 1073741824, 'is_bytes' => true, 'limit_read' => true],
    ]]);
    $failed = Server::factory()->whm()->create();
    ServerUsage::factory()->failed(RemoteServerErrorReason::Timeout)->create(['server_id' => $failed->id]);

    Livewire::test(ManageServers::class)
        ->assertTableColumnStateSet('usage', '21.10.2026 14:30 · 1 uyarı', $nearlyFull)
        ->assertTableColumnStateSet('usage', 'Yenilenemedi: sunucu zamanında yanıt vermedi', $failed);
});

it('shows no reseller line on a cPanel server', function () {
    $server = Server::factory()->cpanel()->create();

    Livewire::test(ManageServers::class)
        ->assertTableColumnDoesNotHaveDescription('usage', 'Bayi: Disk 38 GB / 50 GB', $server);
});
