<?php

use App\Enums\RemoteServerErrorReason;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Widgets\TodayTasks;
use App\Models\AccountUsage;
use App\Models\CustomerService;
use App\Models\Server;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function servicesOf(CustomerService $service)
{
    return Livewire::test(CustomerServicesRelationManager::class, [
        'ownerRecord' => $service->customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

function openUsage(CustomerService $service): TestAction
{
    return TestAction::make('viewUsage')->arguments(['item' => $service->items()->firstOrFail()->id]);
}

function fakeUsageAnswers(): void
{
    Http::fake(function (Request $request) {
        $function = match (true) {
            str_contains($request->url(), 'get_usages') => 'get_usages',
            str_contains($request->url(), 'list_pops_with_disk') => 'list_pops_with_disk',
            default => 'list_databases',
        };

        return Http::response(json_decode(file_get_contents(base_path("tests/Fixtures/cpanel/{$function}.json")), true));
    });
}

it('shows the usage window with gauges, counters, mailboxes and databases', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana WHM']);
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('disk_usage', (int) (1024 ** 3 * 1.2), 5 * 1024 ** 3, true),
        UsageScenario::resource('bandwidth', 3 * 1024 ** 3, null, true),
        UsageScenario::resource('email_accounts', 4, 10),
    ], ['databases' => [['name' => 'logi_wp', 'bytes' => 52428800]]]);
    UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 50, 100);
    UsageScenario::mailbox($server, 'logi', 'satis@logi.com.tr', 99, 100, ['login_suspended' => true]);
    UsageScenario::mailbox($server, 'logi', 'bos@logi.com.tr', 5, null);

    servicesOf($service)
        ->mountAction(openUsage($service))
        ->assertMountedActionModalSee('logi.com.tr')
        ->assertMountedActionModalSee('Ana WHM')
        ->assertMountedActionModalSee(['Disk', '1,2 GB / 5 GB', '%23'])
        ->assertMountedActionModalSee(['Aylık trafik', '3 GB · Limitsiz'])
        ->assertMountedActionModalSee(['E-posta hesabı', '4 / 10'])
        ->assertMountedActionModalSee(['E-posta hesapları (3)', 'satis@logi.com.tr', 'Dolmak üzere', 'Oturum askıda', 'info@logi.com.tr', 'bos@logi.com.tr'])
        ->assertMountedActionModalSee(['Veritabanları (1)', 'logi_wp', '50 MB'])
        ->assertMountedActionModalSee('Şimdi yenile')
        ->assertMountedActionModalSee('Kapat');
});

it('filters the mailboxes of a cPanel window by the domain and notes the shared account', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $first = UsageScenario::linkedService($server, 'logi.com.tr');
    UsageScenario::linkedService($server, 'ikinci.com');
    UsageScenario::usage($server, 'logi');
    UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 1, 100);
    UsageScenario::mailbox($server, 'logi', 'destek@ikinci.com', 1, 100);

    servicesOf($first)
        ->mountAction(openUsage($first))
        ->assertMountedActionModalSee('Hesap geneli · 2 alan adıyla paylaşılıyor')
        ->assertMountedActionModalSee('E-posta hesapları (1)')
        ->assertMountedActionModalSee('info@logi.com.tr')
        ->assertMountedActionModalDontSee('destek@ikinci.com');
});

it('says what to do when the account was never read', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');

    servicesOf($service)
        ->mountAction(openUsage($service))
        ->assertMountedActionModalSee('Henüz okunmadı')
        ->assertMountedActionModalSee('Şimdi yenile');
});

it('explains a failed refresh and keeps showing the old values', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', null, [
        'refreshed_at' => '2026-10-21 08:30:00',
        'failed_at' => '2026-10-21 14:30:00',
        'failure_reason' => RemoteServerErrorReason::Unauthorized,
    ]);

    servicesOf($service)
        ->mountAction(openUsage($service))
        ->assertMountedActionModalSee('Son yenileme başarısız: yetki hatası.')
        ->assertMountedActionModalSee('Gösterilen veriler 21.10.2026 08:30 tarihinden.')
        ->assertMountedActionModalSee('4,7 GB / 5 GB');
});

it('refreshes the account from the window and keeps the window open with the new values', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $service = UsageScenario::linkedService($server, 'logi.com.tr');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('disk_usage', 1, 100, true)]);
    fakeUsageAnswers();

    servicesOf($service)
        ->callAction([openUsage($service), TestAction::make('refreshUsage')])
        ->assertNotified('Kullanım yenilendi.');

    expect(AccountUsage::query()->sole()->resources)->toHaveCount(5);
});

it('tells why the refresh failed', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $service = UsageScenario::linkedService($server, 'logi.com.tr');
    Http::fake(['*' => Http::response('no', 401)]);

    servicesOf($service)
        ->callAction([openUsage($service), TestAction::make('refreshUsage')])
        ->assertNotified('Son yenileme başarısız: yetki hatası.');

    expect(AccountUsage::query()->sole()->failure_reason)->toBe(RemoteServerErrorReason::Unauthorized);
});

it('shows the badge on the service row only over the threshold, worst first', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('disk_usage', 94, 100, true),
        UsageScenario::resource('email_accounts', 10, 10),
    ]);
    UsageScenario::mailbox($server, 'logi', 'a@logi.com.tr', 100, 100);

    servicesOf($service)
        ->assertSee('E-posta hesabı %100')
        ->assertSee('2 sorun daha')
        ->assertSeeHtml("mountAction('viewUsage', { item: {$service->items()->firstOrFail()->id} })");
});

it('shows no badge below the threshold or without a link', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('disk_usage', 50, 100, true)]);
    $plain = CustomerService::factory()->for($service->customer)->create();

    servicesOf($service)
        ->assertCanSeeTableRecords([$service, $plain])
        ->assertDontSeeHtml("mountAction('viewUsage'");
});

it('puts the usage summary and the button in the edit window of a linked item only', function () {
    $server = Server::factory()->whm()->create();
    $linked = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('disk_usage', (int) (1024 ** 3 * 1.2), 5 * 1024 ** 3, true)], ['refreshed_at' => '2026-10-21 14:30:00']);
    UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 1, 100);
    $plain = CustomerService::factory()->for($linked->customer)->create();

    servicesOf($linked)
        ->mountAction(TestAction::make('editService')->table($linked))
        ->assertMountedActionModalSee('Disk 1,2 GB / 5 GB · 1 e-posta · 14:30')
        ->assertMountedActionModalSee('Kullanımı gör');

    servicesOf($linked)
        ->mountAction(TestAction::make('editService')->table($plain))
        ->assertMountedActionModalDontSee('Kullanımı gör');
});

it('opens the window from a Bugün quota row', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    UsageScenario::usage($server, 'logi');

    Livewire::test(TodayTasks::class)
        ->assertSee('Kullanımı gör')
        ->mountAction(openUsage($service))
        ->assertMountedActionModalSee('4,7 GB / 5 GB');
});
