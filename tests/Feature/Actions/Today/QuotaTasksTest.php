<?php

use App\Actions\Today\ListTodayTasks;
use App\Models\Server;
use Tests\Fixtures\UsageScenario;

it('lists hosting quota warnings, fullest first, with the total', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('disk_usage', 92, 100, true),
        UsageScenario::resource('email_accounts', 10, 10),
        UsageScenario::resource('mysql_databases', 1, 10),
    ]);

    $tasks = app(ListTodayTasks::class)->handle();

    expect($tasks['quota']['total'])->toBe(2)
        ->and($tasks['quota']['items']->map(fn ($alert): string => $alert->subject)->all())->toBe(['E-posta hesabı', 'Disk'])
        ->and($tasks['overdue']['total'])->toBe(0);
});

it('caps the quota section at the limit while the total counts everything', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', []);

    foreach (range(1, 4) as $number) {
        UsageScenario::mailbox($server, 'logi', "kutu{$number}@logi.com.tr", 95, 100);
    }

    $tasks = app(ListTodayTasks::class)->handle(3);

    expect($tasks['quota']['items'])->toHaveCount(3)
        ->and($tasks['quota']['total'])->toBe(4);
});

it('has an empty quota section without warnings', function () {
    $tasks = app(ListTodayTasks::class)->handle();

    expect($tasks['quota'])->toMatchArray(['total' => 0])
        ->and($tasks['quota']['items'])->toBeEmpty();
});
