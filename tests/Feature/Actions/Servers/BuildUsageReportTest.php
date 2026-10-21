<?php

use App\Actions\Servers\BuildUsageReport;
use App\Enums\UsageLevel;
use App\Models\CustomerService;
use App\Models\Server;
use Tests\Fixtures\UsageScenario;

it('orders the mailboxes fullest first and the unlimited ones last', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi');
    UsageScenario::mailbox($server, 'logi', 'bos@logi.com.tr', 5, null);
    UsageScenario::mailbox($server, 'logi', 'info@logi.com.tr', 50, 100);
    UsageScenario::mailbox($server, 'logi', 'satis@logi.com.tr', 99, 100);

    $report = app(BuildUsageReport::class)->handle($service->items()->firstOrFail());

    expect(array_map(fn ($mailbox): string => $mailbox->address, $report->mailboxes))
        ->toBe(['satis@logi.com.tr', 'info@logi.com.tr', 'bos@logi.com.tr'])
        ->and($report->mailboxes[0]->meter->level)->toBe(UsageLevel::NearlyFull)
        ->and($report->mailboxes[2]->meter->usageText())->toBe('5 B · Limitsiz');
});

it('splits the gauges from the counters and gives momentary resources no level', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('email_accounts', 10, 10),
        UsageScenario::resource('bandwidth', 10, 100, true),
        UsageScenario::resource('disk_usage', 95, 100, true),
        UsageScenario::resource('lvecpu', 100, 100),
    ]);

    $report = app(BuildUsageReport::class)->handle($service->items()->firstOrFail());

    expect(array_map(fn ($meter): string => $meter->id, $report->gauges))->toBe(['disk_usage', 'bandwidth'])
        ->and(array_map(fn ($meter): string => $meter->id, $report->counters))->toBe(['email_accounts', 'lvecpu'])
        ->and($report->counters[1]->level)->toBeNull()
        ->and($report->badge()['text'])->toBe('E-posta hesabı %100 · 1 sorun daha');
});

it('has no report for an item that is not linked to a server account', function () {
    $service = CustomerService::factory()->create();

    expect(app(BuildUsageReport::class)->handle($service->items()->firstOrFail()))->toBeNull();
});
