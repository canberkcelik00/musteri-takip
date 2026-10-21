<?php

use App\Actions\Digest\BuildDigest;
use App\Actions\Servers\ListUsageAlerts;
use App\Actions\Servers\RefreshServerTotals;
use App\Actions\Servers\RefreshStaleServerTotals;
use App\Actions\Servers\SendQuotaWarnings;
use App\Enums\RemoteServerErrorReason;
use App\Enums\SettingKey;
use App\Mail\QuotaWarningMail;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Answers the reseller calls of any WHM server from the fixtures.
 */
function fakeResellerTotals(): void
{
    Http::fake(function ($request) {
        $call = match (true) {
            str_contains($request->url(), 'resellerstats') => 'resellerstats',
            str_contains($request->url(), 'acctcounts') => 'acctcounts',
            str_contains($request->url(), 'showbw') => 'showbw',
            default => 'listaccts',
        };

        return Http::response(json_decode(file_get_contents(base_path("tests/Fixtures/whm/{$call}.json")), true));
    });
}

/**
 * Stored totals of a server whose disk is `$diskPercent` percent of a 50 GB limit.
 */
function totalsAtDisk(Server $server, int $diskPercent, bool $limitRead = true): ServerUsage
{
    $limit = 50 * 1073741824;

    return ServerUsage::factory()->create([
        'server_id' => $server->id,
        'resources' => [
            ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => intdiv($limit * $diskPercent, 100), 'limit' => $limit, 'is_bytes' => true, 'limit_read' => $limitRead],
        ],
    ]);
}

it('reads and stores the totals of a server', function () {
    fakeResellerTotals();
    $server = Server::factory()->whm()->create();

    $usage = app(RefreshServerTotals::class)->handle($server);

    expect($usage->failure_reason)->toBeNull()
        ->and($usage->refreshed_at)->not->toBeNull()
        ->and($usage->resources)->toHaveCount(3)
        ->and($usage->resources[2])->toMatchArray(['id' => 'reseller_accounts', 'used' => 24, 'limit' => 30]);

    app(RefreshServerTotals::class)->handle($server);

    expect(ServerUsage::query()->count())->toBe(1);
});

it('keeps the earlier totals and records the reason when the server cannot be read', function () {
    $server = Server::factory()->whm()->create();
    $earlier = totalsAtDisk($server, 50);
    Http::fake(['*' => Http::response('denied', 401)]);

    $usage = app(RefreshServerTotals::class)->handle($server);

    expect($usage->failure_reason)->toBe(RemoteServerErrorReason::Unauthorized)
        ->and($usage->failed_at)->not->toBeNull()
        ->and($usage->resources)->toEqual($earlier->resources)
        ->and(ServerUsage::query()->count())->toBe(1);
});

it('refreshes a server again only after the configured interval', function () {
    fakeResellerTotals();
    $this->travelTo('2026-10-21 09:00:00');
    Server::factory()->whm()->create();

    expect(app(RefreshStaleServerTotals::class)->handle()->refreshed)->toBe(1);

    $this->travelTo('2026-10-21 09:59:00');
    expect(app(RefreshStaleServerTotals::class)->handle()->refreshed)->toBe(0);

    $this->travelTo('2026-10-21 10:00:00');
    expect(app(RefreshStaleServerTotals::class)->handle()->refreshed)->toBe(1);

    app(Settings::class)->set(SettingKey::UsageRefreshMinutes, '180');

    $this->travelTo('2026-10-21 12:59:00');
    expect(app(RefreshStaleServerTotals::class)->handle()->refreshed)->toBe(0);

    $this->travelTo('2026-10-21 13:00:00');
    expect(app(RefreshStaleServerTotals::class)->handle()->refreshed)->toBe(1);
});

it('counts a failed attempt as an attempt', function () {
    Http::fake(['*' => Http::response('broken', 500)]);
    $this->travelTo('2026-10-21 09:00:00');
    Server::factory()->whm()->create();

    $first = app(RefreshStaleServerTotals::class)->handle();
    $second = app(RefreshStaleServerTotals::class)->handle();

    expect($first->failed)->toBe(1)
        ->and($second->failed)->toBe(0);
});

it('skips cPanel and archived servers and removes the totals of an archived one', function () {
    fakeResellerTotals();
    Server::factory()->cpanel()->create();
    $archived = Server::factory()->whm()->archived()->create();
    ServerUsage::factory()->create(['server_id' => $archived->id]);

    $result = app(RefreshStaleServerTotals::class)->handle();

    expect($result->refreshed)->toBe(0)
        ->and($result->removed)->toBe(1)
        ->and(ServerUsage::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('tells what the command did for the servers', function () {
    fakeResellerTotals();
    Server::factory()->whm()->create();

    $this->artisan('app:refresh-server-usage')
        ->expectsOutputToContain('Bayi toplamı: 1 sunucu yenilendi, 0 sunucuda hata var, 0 eski kayıt silindi.')
        ->assertSuccessful();
});

it('lists a reseller total at or above the threshold as a server alert', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);
    totalsAtDisk($server, 94);

    $alerts = app(ListUsageAlerts::class)->handle();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]->isServer())->toBeTrue()
        ->and($alerts[0]->serverName)->toBe('Ana sunucu')
        ->and($alerts[0]->subject)->toBe('Bayi diski')
        ->and($alerts[0]->percent())->toBe(94)
        ->and($alerts[0]->owners)->toBe([])
        ->and($alerts[0]->accountUsageId)->toBeNull();
});

it('does not alert for a limit that was not read, an unlimited one, a low use or an archived server', function () {
    totalsAtDisk(Server::factory()->whm()->create(), 99, limitRead: false);
    totalsAtDisk(Server::factory()->whm()->create(), 50);
    totalsAtDisk(Server::factory()->whm()->archived()->create(), 99);
    ServerUsage::factory()->create(['server_id' => Server::factory()->whm()]);

    expect(app(ListUsageAlerts::class)->handle())->toBeEmpty();
});

it('e-mails a server alert once and again only when it gets worse', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);
    $usage = totalsAtDisk($server, 94);

    expect(app(SendQuotaWarnings::class)->handle())->toBe(1);
    Mail::assertSent(QuotaWarningMail::class, fn (QuotaWarningMail $mail): bool => $mail->alerts->first()->isServer()
        && str_contains($mail->render(), 'Sunucu: Ana sunucu'));

    expect($usage->fresh()->warned_levels)->toBe(['reseller_disk' => 'nearly_full'])
        ->and(app(SendQuotaWarnings::class)->handle())->toBe(0);

    $resources = $usage->resources;
    $resources[0]['used'] = $resources[0]['limit'];
    $usage->update(['resources' => $resources]);

    expect(app(SendQuotaWarnings::class)->handle())->toBe(1)
        ->and($usage->fresh()->warned_levels)->toBe(['reseller_disk' => 'full']);
});

it('announces a server alert again after it dropped under the threshold', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
    $usage = totalsAtDisk(Server::factory()->whm()->create(), 94);

    app(SendQuotaWarnings::class)->handle();

    $resources = $usage->resources;
    $resources[0]['used'] = 1;
    $usage->update(['resources' => $resources]);

    expect(app(SendQuotaWarnings::class)->handle())->toBe(0)
        ->and($usage->fresh()->warned_levels)->toBeNull();

    $resources[0]['used'] = $resources[0]['limit'] - 1;
    $usage->update(['resources' => $resources]);

    expect(app(SendQuotaWarnings::class)->handle())->toBe(1);
});

it('puts the server alert into the digest', function () {
    $server = Server::factory()->whm()->create(['name' => 'Ana sunucu']);
    totalsAtDisk($server, 94);

    $digest = app(BuildDigest::class)->handle(now()->subMonth(), now());

    expect($digest['quota_alerts'])->toHaveCount(1)
        ->and($digest['quota_alerts'][0]->serverName)->toBe('Ana sunucu');
});
