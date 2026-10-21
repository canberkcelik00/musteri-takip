<?php

use App\Enums\RemoteServerErrorReason;
use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Models\Server;
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
