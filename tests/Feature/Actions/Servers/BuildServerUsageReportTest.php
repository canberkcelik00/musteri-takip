<?php

use App\Actions\Servers\BuildServerUsageReport;
use App\Enums\RemoteServerErrorReason;
use App\Enums\SettingKey;
use App\Enums\UsageLevel;
use App\Models\Customer;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Support\Settings;
use Tests\Fixtures\UsageScenario;

/**
 * A stored account row; only what a test cares about is given.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storedAccount(string $username, array $overrides = []): array
{
    return [
        'username' => $username,
        'domain' => "{$username}.com",
        'plan' => null,
        'suspended' => false,
        'suspend_reason' => null,
        'disk' => 1048576,
        'disk_limit' => null,
        'bw' => null,
        'bw_limit' => null,
        ...$overrides,
    ];
}

it('has no report for a cPanel connection', function () {
    expect(app(BuildServerUsageReport::class)->handle(Server::factory()->cpanel()->create()))->toBeNull();
});

it('says nothing was read yet', function () {
    $report = app(BuildServerUsageReport::class)->handle(Server::factory()->whm()->create());

    expect($report->hasData())->toBeFalse()
        ->and($report->accounts)->toBe([])
        ->and($report->gauges)->toBe([]);
});

it('builds the gauges, the counts and the accounts from the stored data', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu', 'hostname' => 'sunucu.example.com']);
    ServerUsage::factory()->create(['server_id' => $server->id]);

    $report = app(BuildServerUsageReport::class)->handle($server);

    expect($report->serverName)->toBe('Ana sunucu')
        ->and($report->hostname)->toBe('sunucu.example.com')
        ->and(array_map(fn ($meter): string => $meter->usageText(), $report->gauges))->toBe(['38 GB / 50 GB', '120 GB · Limitsiz', '24 / 30'])
        ->and($report->limitsRead)->toBeTrue()
        ->and([$report->activeCount, $report->suspendedCount])->toBe([1, 1])
        ->and($report->allocatedDisk)->toBe(5368709120)
        ->and($report->unlimitedDiskAccounts)->toBe(1)
        ->and($report->resellerDiskLimit)->toBe(53687091200)
        ->and($report->allocatedBandwidth)->toBe(10737418240)
        ->and($report->unlimitedBandwidthAccounts)->toBe(1)
        ->and($report->resellerBandwidthLimit)->toBeNull();
});

it('puts the accounts at the threshold first, then the biggest disk', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id, 'accounts' => [
        storedAccount('buyuk', ['disk' => 3 * 1073741824]),
        storedAccount('kucuk'),
        storedAccount('dolu', ['disk' => 100, 'disk_limit' => 100]),
        storedAccount('sinirda', ['disk' => 95, 'disk_limit' => 100]),
    ]]);

    $report = app(BuildServerUsageReport::class)->handle($server);

    expect(array_map(fn ($row): string => $row->username, $report->accounts))->toBe(['dolu', 'sinirda', 'buyuk', 'kucuk'])
        ->and($report->accounts[0]->level())->toBe(UsageLevel::Full)
        ->and($report->accounts[1]->level())->toBe(UsageLevel::NearlyFull)
        ->and($report->accounts[2]->level())->toBeNull()
        ->and($report->accounts[2]->bandwidth)->toBeNull()
        ->and($report->unlimitedBandwidthAccounts)->toBe(0);
});

it('uses the warning threshold setting for the account levels', function () {
    app(Settings::class)->set(SettingKey::UsageWarningPercent, '99');
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id, 'accounts' => [
        storedAccount('a', ['disk' => 95, 'disk_limit' => 100]),
    ]]);

    expect(app(BuildServerUsageReport::class)->handle($server)->accounts[0]->level())->toBeNull();
});

it('names the customer an account is linked to and leaves the others unlinked', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id]);
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::linkedService(Server::factory()->whm()->create(), 'askida');

    $report = app(BuildServerUsageReport::class)->handle($server);
    $rows = collect($report->accounts)->keyBy('username');

    expect($rows['logi']->customers)->toBe([['id' => $service->customer_id, 'short_name' => $service->customer->short_name, 'archived' => false]])
        ->and($rows['askida']->customers)->toBe([])
        ->and([$report->linkedCount, $report->unlinkedCount])->toBe([1, 1]);
});

it('still names an archived customer, marked as archived', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id]);
    UsageScenario::linkedService($server, 'logi', ['customer_id' => Customer::factory()->archived()]);

    $rows = collect(app(BuildServerUsageReport::class)->handle($server)->accounts)->keyBy('username');

    expect($rows['logi']->customers[0]['archived'])->toBeTrue();
});

it('gives no level and no percent for a limit the server did not tell', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->create(['server_id' => $server->id, 'resources' => [
        ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => 40802189312, 'limit' => null, 'is_bytes' => true, 'limit_read' => false],
    ]]);

    $report = app(BuildServerUsageReport::class)->handle($server);

    expect($report->limitsRead)->toBeFalse()
        ->and($report->gauges[0]->usageText())->toBe('38 GB')
        ->and($report->gauges[0]->level)->toBeNull()
        ->and($report->resellerDiskLimit)->toBeNull();
});

it('keeps the old data and reports the failure of the last read', function () {
    $server = Server::factory()->whm()->create();
    ServerUsage::factory()->failed(RemoteServerErrorReason::Unauthorized)->create(['server_id' => $server->id]);

    $report = app(BuildServerUsageReport::class)->handle($server);

    expect($report->failureReason)->toBe(RemoteServerErrorReason::Unauthorized)
        ->and($report->hasData())->toBeTrue()
        ->and($report->accounts)->toHaveCount(2);
});
