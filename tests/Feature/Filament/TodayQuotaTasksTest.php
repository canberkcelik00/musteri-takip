<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Widgets\TodayTasks;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('shows a quota row with the customer, the subject, the level and a button', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    UsageScenario::usage($server, 'logi');
    UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 100, 100);

    $widget = Livewire::test(TodayTasks::class);

    $widget->assertSeeInOrder([
        $service->customer->short_name, 'info@logi.com.tr kotası %100', 'Dolu',
        $service->customer->short_name, 'Disk %94 · logi.com.tr', 'Dolmak üzere',
    ])
        ->assertSee('Kullanımı gör')
        ->assertSee('2 iş')
        ->assertDontSee('Bugün yapılacak bir şey yok');

    expect($widget->html())->toContain(CustomerResource::getUrl('view', ['record' => $service->customer]));
});

it('puts the quota rows after the charges', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi');
    Charge::factory()->for(Customer::factory()->create(['short_name' => 'Gecikmis Musteri']))->create(['due_date' => today()->subDays(3)]);

    Livewire::test(TodayTasks::class)->assertSeeInOrder(['Gecikmis Musteri', 'Disk %94']);
});

it('says how many shared customers a cPanel disk warning concerns', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    UsageScenario::linkedService($server, 'logi.com.tr');
    UsageScenario::linkedService($server, 'ikinci.com');
    UsageScenario::usage($server, 'logi');

    Livewire::test(TodayTasks::class)
        ->assertSee('2 müşteriyle paylaşılıyor')
        ->assertSee('1 iş');
});

it('notes how many quota warnings did not fit in the list', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', []);

    foreach (range(1, 12) as $number) {
        UsageScenario::mailbox($server, 'logi', "kutu{$number}@logi.com.tr", 95, 100);
    }

    Livewire::test(TodayTasks::class)
        ->assertSee('12 iş')
        ->assertSee('Hosting kotası: 2 uyarı daha');
});

it('is still empty without any warning', function () {
    Livewire::test(TodayTasks::class)->assertSee('Bugün yapılacak bir şey yok');
});
