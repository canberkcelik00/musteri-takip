<?php

use App\Actions\Servers\RefreshAccountUsage;
use App\Actions\Servers\RefreshStaleUsages;
use App\Enums\RemoteServerErrorReason;
use App\Enums\SettingKey;
use App\Models\AccountUsage;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Mailbox;
use App\Models\Server;
use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Answers the three usage calls of any server from the cPanel fixtures; the WHM port gets the
 * answer wrapped in `data.uapi`. Users in `$brokenUsers` get a server error.
 *
 * @param  list<string>  $brokenUsers
 */
function fakeServerUsage(array $brokenUsers = []): void
{
    Http::fake(function (Request $request) use ($brokenUsers) {
        $url = $request->url();

        foreach ($brokenUsers as $user) {
            if (str_contains($url, "cpanel.user={$user}")) {
                return Http::response('broken', 500);
            }
        }

        $function = match (true) {
            str_contains($url, 'get_usages') => 'get_usages',
            str_contains($url, 'list_pops_with_disk') => 'list_pops_with_disk',
            str_contains($url, 'list_domains') => 'list_domains',
            default => 'list_databases',
        };
        $body = json_decode(file_get_contents(base_path("tests/Fixtures/cpanel/{$function}.json")), true);

        return Http::response(str_contains($url, ':2087/') ? ['metadata' => ['result' => 1], 'data' => ['uapi' => $body]] : $body);
    });
}

/**
 * A customer service whose first item is linked to the account `$key` of the server.
 *
 * @param  array<string, mixed>  $attributes
 */
function linkedHostingService(Server $server, string $key, array $attributes = []): CustomerService
{
    $service = CustomerService::factory()->create($attributes);
    $service->items()->firstOrFail()->forceFill(['server_id' => $server->id, 'remote_key' => $key])->save();

    return $service;
}

function usageCallsTo(string $user): int
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), "cpanel.user={$user}"))->count();
}

it('reads the usage of the accounts of linked active services', function () {
    fakeServerUsage();
    $server = Server::factory()->whm()->create();
    linkedHostingService($server, 'logi');
    linkedHostingService($server, 'ikinci');

    $result = app(RefreshStaleUsages::class)->handle();

    expect($result->refreshed)->toBe(2)
        ->and($result->failed)->toBe(0)
        ->and($result->remaining)->toBe(0)
        ->and(AccountUsage::query()->orderBy('remote_username')->pluck('remote_username')->all())->toBe(['ikinci', 'logi'])
        ->and(Mailbox::query()->where('remote_username', 'logi')->count())->toBe(3);
});

it('refreshes an account again only after an hour', function () {
    fakeServerUsage();
    $this->travelTo('2026-10-21 09:00:00');
    linkedHostingService(Server::factory()->whm()->create(), 'logi');

    app(RefreshStaleUsages::class)->handle();
    expect(usageCallsTo('logi'))->toBe(4);

    $this->travelTo('2026-10-21 09:59:00');
    $result = app(RefreshStaleUsages::class)->handle();
    expect($result->refreshed)->toBe(0)
        ->and(usageCallsTo('logi'))->toBe(4);

    $this->travelTo('2026-10-21 10:00:00');
    $result = app(RefreshStaleUsages::class)->handle();
    expect($result->refreshed)->toBe(1)
        ->and(usageCallsTo('logi'))->toBe(8)
        ->and(AccountUsage::query()->count())->toBe(1)
        ->and(Mailbox::query()->count())->toBe(3);
});

it('counts a failed attempt as an attempt, so a broken server is not asked on every run', function () {
    fakeServerUsage(['logi']);
    $this->travelTo('2026-10-21 09:00:00');
    linkedHostingService(Server::factory()->whm()->create(), 'logi');

    $first = app(RefreshStaleUsages::class)->handle();
    $second = app(RefreshStaleUsages::class)->handle();

    expect($first->failed)->toBe(1)
        ->and($second->failed)->toBe(0)
        ->and(usageCallsTo('logi'))->toBe(1);

    $this->travelTo('2026-10-21 10:00:00');

    expect(app(RefreshStaleUsages::class)->handle()->failed)->toBe(1)
        ->and(usageCallsTo('logi'))->toBe(2);
});

it('lets one failing account leave the others alone', function () {
    fakeServerUsage(['kirik']);
    $server = Server::factory()->whm()->create();
    linkedHostingService($server, 'kirik');
    linkedHostingService($server, 'saglam');

    $result = app(RefreshStaleUsages::class)->handle();

    expect($result->refreshed)->toBe(1)
        ->and($result->failed)->toBe(1)
        ->and(AccountUsage::query()->where('remote_username', 'kirik')->firstOrFail()->failure_reason)->toBe(RemoteServerErrorReason::InvalidResponse)
        ->and(AccountUsage::query()->where('remote_username', 'saglam')->firstOrFail()->refreshed_at)->not->toBeNull();
});

it('stops when the time budget is used up and finishes the rest on the next runs', function () {
    fakeServerUsage();
    $server = Server::factory()->whm()->create();
    foreach (['bir', 'iki', 'uc'] as $key) {
        linkedHostingService($server, $key);
    }

    $runs = [
        app(RefreshStaleUsages::class)->handle(0),
        app(RefreshStaleUsages::class)->handle(0),
        app(RefreshStaleUsages::class)->handle(0),
        app(RefreshStaleUsages::class)->handle(0),
    ];

    expect(array_map(fn ($run): array => [$run->refreshed, $run->remaining], $runs))->toBe([[1, 2], [1, 1], [1, 0], [0, 0]])
        ->and(AccountUsage::query()->count())->toBe(3)
        ->and(usageCallsTo('bir') + usageCallsTo('iki') + usageCallsTo('uc'))->toBe(12);
});

it('reads accounts never read before the oldest ones', function () {
    fakeServerUsage();
    $this->travelTo('2026-10-21 09:00:00');
    $server = Server::factory()->whm()->create();
    linkedHostingService($server, 'eski');
    app(RefreshStaleUsages::class)->handle();

    $this->travelTo('2026-10-21 16:00:00');
    linkedHostingService($server, 'yeni');
    $result = app(RefreshStaleUsages::class)->handle(0);

    expect($result->refreshed)->toBe(1)
        ->and($result->remaining)->toBe(1)
        ->and(AccountUsage::query()->where('remote_username', 'yeni')->firstOrFail()->refreshed_at->toDateTimeString())->toBe('2026-10-21 16:00:00')
        ->and(AccountUsage::query()->where('remote_username', 'eski')->firstOrFail()->refreshed_at->toDateTimeString())->toBe('2026-10-21 09:00:00');
});

it('keeps one account row for all domains of a cPanel connection', function () {
    fakeServerUsage();
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    linkedHostingService($server, 'logi.com.tr');
    linkedHostingService($server, 'ikinci.com');

    $result = app(RefreshStaleUsages::class)->handle();

    expect($result->refreshed)->toBe(1)
        ->and(AccountUsage::query()->count())->toBe(1)
        ->and(AccountUsage::query()->firstOrFail()->remote_username)->toBe('logi')
        ->and(Mailbox::query()->count())->toBe(3);

    Http::assertSentCount(3);
});

it('skips what no linked, active service needs', function () {
    fakeServerUsage();
    $server = Server::factory()->whm()->create();
    linkedHostingService($server, 'iptal', ['status' => 'cancelled', 'cancelled_on' => now()->toDateString()]);
    linkedHostingService($server, 'arsivli', ['customer_id' => Customer::factory()->archived()]);
    linkedHostingService(Server::factory()->whm()->archived()->create(), 'arsivli-sunucu');
    CustomerService::factory()->create();

    $result = app(RefreshStaleUsages::class)->handle();

    expect($result->refreshed)->toBe(0);

    Http::assertNothingSent();
});

it('removes stored usage that no linked, active service needs any more', function () {
    fakeServerUsage();
    $server = Server::factory()->whm()->create();
    $service = linkedHostingService($server, 'logi');
    AccountUsage::factory()->create(['server_id' => $server->id, 'remote_username' => 'eski']);
    Mailbox::factory()->create(['server_id' => $server->id, 'remote_username' => 'eski']);

    $first = app(RefreshStaleUsages::class)->handle();

    expect($first->removed)->toBe(2)
        ->and(AccountUsage::query()->pluck('remote_username')->all())->toBe(['logi']);

    $service->update(['status' => 'cancelled', 'cancelled_on' => now()->toDateString()]);
    $second = app(RefreshStaleUsages::class)->handle();

    expect($second->removed)->toBe(4)
        ->and(AccountUsage::query()->count())->toBe(0)
        ->and(Mailbox::query()->count())->toBe(0);
});

it('reads an account on demand with the same action', function () {
    fakeServerUsage();
    $server = Server::factory()->whm()->create();

    $usage = app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($usage->failure_reason)->toBeNull()
        ->and($usage->resources)->toHaveCount(5)
        ->and($usage->resources[0])->toBe(['id' => 'disk_usage', 'label' => 'Disk Usage', 'used' => 1288490188, 'limit' => 5368709120, 'is_bytes' => true])
        ->and($usage->databases)->toBe([['name' => 'logi_wp', 'bytes' => 52428800], ['name' => 'logi_shop', 'bytes' => 1048576]]);
});

it('tells what the command did', function () {
    fakeServerUsage();
    $server = Server::factory()->whm()->create();
    linkedHostingService($server, 'logi');
    AccountUsage::factory()->create(['server_id' => $server->id, 'remote_username' => 'eski']);

    $this->artisan('app:refresh-server-usage')
        ->expectsOutputToContain('Kullanım: 1 hesap yenilendi, 0 hesapta hata var, 0 hesap sonraki çalışmaya kaldı, 1 eski kayıt silindi.')
        ->assertSuccessful();

    $this->artisan('app:refresh-server-usage')
        ->expectsOutputToContain('Kullanım: 0 hesap yenilendi')
        ->assertSuccessful();
});

it('uses the usage refresh interval of the settings', function () {
    fakeServerUsage();
    app(Settings::class)->set(SettingKey::UsageRefreshMinutes, '180');
    $this->travelTo('2026-10-21 09:00:00');
    linkedHostingService(Server::factory()->whm()->create(), 'logi');

    app(RefreshStaleUsages::class)->handle();

    $this->travelTo('2026-10-21 11:59:00');
    expect(app(RefreshStaleUsages::class)->handle()->refreshed)->toBe(0);

    $this->travelTo('2026-10-21 12:00:00');
    expect(app(RefreshStaleUsages::class)->handle()->refreshed)->toBe(1);
});
