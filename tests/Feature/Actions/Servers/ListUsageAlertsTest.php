<?php

use App\Actions\Servers\ListUsageAlerts;
use App\Enums\SettingKey;
use App\Enums\UsageLevel;
use App\Models\Customer;
use App\Models\Server;
use App\Support\Settings;
use Tests\Fixtures\UsageScenario;

function usageAlerts()
{
    return app(ListUsageAlerts::class)->handle();
}

it('lists a resource at or above the threshold with its owner', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi', domain: 'logi.com.tr');
    UsageScenario::usage($server, 'logi');

    $alerts = usageAlerts();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->subject)->toBe('Disk')
        ->and($alerts[0]->resourceId)->toBe('disk_usage')
        ->and($alerts[0]->level)->toBe(UsageLevel::NearlyFull)
        ->and($alerts[0]->percent())->toBe(94)
        ->and($alerts[0]->usageText())->toBe('4,7 GB / 5 GB')
        ->and($alerts[0]->warnedLevel)->toBeNull()
        ->and($alerts[0]->owners)->toBe([[
            'item_id' => $service->items()->firstOrFail()->id,
            'customer_id' => $service->customer_id,
            'short_name' => $service->customer->short_name,
            'domain' => 'logi.com.tr',
        ]]);
});

it('lists nothing below the threshold, without a limit or for momentary resources', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('disk_usage', 100, 1000, true),
        UsageScenario::resource('bandwidth', 999999, null, true),
        UsageScenario::resource('lvecpu', 100, 100),
        UsageScenario::resource('email_accounts', 4, 10),
    ]);

    expect(usageAlerts())->toBeEmpty();
});

it('counts counters such as the e-mail accounts and uses the Turkish name', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('email_accounts', 10, 10)]);

    $alerts = usageAlerts();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->subject)->toBe('E-posta hesabı')
        ->and($alerts[0]->level)->toBe(UsageLevel::Full)
        ->and($alerts[0]->usageText())->toBe('10 / 10');
});

it('keeps the server description for a resource it has no name for', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('yeni_kaynak', 10, 10)]);

    expect(usageAlerts()[0]->subject)->toBe('Yeni_kaynak');
});

it('lists a mailbox with a quota at the threshold and none without a quota', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', []);
    UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 970, 1000);
    UsageScenario::mailbox($server, 'logi', 'sessiz@logi.com.tr', 500, 1000);
    UsageScenario::mailbox($server, 'logi', 'limitsiz@logi.com.tr', 999999, null);

    $alerts = usageAlerts();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->subject)->toBe('info@logi.com.tr')
        ->and($alerts[0]->mailboxId)->not->toBeNull()
        ->and($alerts[0]->resourceId)->toBeNull()
        ->and($alerts[0]->percent())->toBe(97);
});

it('lists the fullest first', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('disk_usage', 92, 100, true),
        UsageScenario::resource('email_accounts', 10, 10),
        UsageScenario::resource('mysql_databases', 95, 100),
    ]);
    UsageScenario::mailbox($server, 'logi', 'a@logi.com.tr', 99, 100);

    expect(usageAlerts()->map(fn ($alert): string => $alert->subject)->all())
        ->toBe(['E-posta hesabı', 'a@logi.com.tr', 'Veritabanı', 'Disk']);
});

it('follows the configured threshold', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('disk_usage', 60, 100, true)]);

    expect(usageAlerts())->toBeEmpty();

    app(Settings::class)->set(SettingKey::UsageWarningPercent, '50');

    expect(usageAlerts())->toHaveCount(1);
});

it('skips what no linked, active service needs and what was never read', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'iptal', ['status' => 'cancelled', 'cancelled_on' => now()->toDateString()]);
    UsageScenario::linkedService($server, 'arsivli', ['customer_id' => Customer::factory()->archived()]);
    UsageScenario::linkedService($server, 'okunmamis');
    foreach (['iptal', 'arsivli', 'yetim'] as $username) {
        UsageScenario::usage($server, $username);
    }
    UsageScenario::usage($server, 'okunmamis', null, ['refreshed_at' => null, 'failed_at' => now()]);

    expect(usageAlerts())->toBeEmpty();
});

it('gives a shared cPanel disk to every customer of the connection and a mailbox to its domain', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $first = UsageScenario::linkedService($server, 'logi.com.tr');
    $second = UsageScenario::linkedService($server, 'ikinci.com');
    UsageScenario::usage($server, 'logi');
    UsageScenario::mailbox($server, 'logi', 'destek@ikinci.com', 99, 100);

    $alerts = usageAlerts()->keyBy('subject');

    expect(collect($alerts['Disk']->owners)->pluck('customer_id')->all())->toEqualCanonicalizing([$first->customer_id, $second->customer_id])
        ->and(collect($alerts['destek@ikinci.com']->owners)->pluck('customer_id')->all())->toBe([$second->customer_id]);
});

it('gives a mailbox of an unknown domain to the owners of the account', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $service = UsageScenario::linkedService($server, 'logi.com.tr');
    UsageScenario::usage($server, 'logi', []);
    UsageScenario::mailbox($server, 'logi', 'x@baska.net', 99, 100);

    expect(collect(usageAlerts()[0]->owners)->pluck('customer_id')->all())->toBe([$service->customer_id]);
});
