<?php

use App\Actions\Servers\RefreshAccountUsage;
use App\Enums\RemoteServerErrorReason;
use App\Models\AccountUsage;
use App\Models\Mailbox;
use App\Models\Server;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fakes a cPanel server and returns the state the answers are read from, so a test can change them:
 * `overrides` maps a fixture name to another body, `status` is the HTTP status of every answer.
 */
function fakeCpanelUsage(): ArrayObject
{
    $state = new ArrayObject(['overrides' => [], 'status' => 200]);

    Http::fake(function (Request $request) use ($state) {
        if ($state['status'] !== 200) {
            return Http::response('denied', $state['status']);
        }

        $function = match (true) {
            str_contains($request->url(), 'get_usages') => 'get_usages',
            str_contains($request->url(), 'list_pops_with_disk') => 'list_pops_with_disk',
            default => 'list_databases',
        };

        return Http::response($state['overrides'][$function] ?? json_decode(file_get_contents(base_path("tests/Fixtures/cpanel/{$function}.json")), true));
    });

    return $state;
}

it('stores the resources, databases and mailboxes of an account', function () {
    fakeCpanelUsage();
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $this->travelTo('2026-10-21 09:30:15');

    $usage = app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($usage->refreshed_at->toDateTimeString())->toBe('2026-10-21 09:30:15')
        ->and($usage->failed_at)->toBeNull()
        ->and($usage->resources)->toHaveCount(5)
        ->and($usage->databases)->toHaveCount(2)
        ->and(Mailbox::query()->count())->toBe(3);

    $mailbox = Mailbox::query()->where('address', 'satis@logi.com.tr')->firstOrFail();

    expect($mailbox->domain)->toBe('logi.com.tr')
        ->and($mailbox->used_bytes)->toBe(1069547520)
        ->and($mailbox->quota_bytes)->toBeNull()
        ->and($mailbox->login_suspended)->toBeTrue();
});

it('updates in place and keeps the warning marks when read again', function () {
    fakeCpanelUsage();
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    app(RefreshAccountUsage::class)->handle($server, 'logi');
    $marked = now()->subDay()->startOfSecond();
    AccountUsage::query()->update(['warning_sent_at' => $marked]);
    Mailbox::query()->update(['warning_sent_at' => $marked]);

    app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect(AccountUsage::query()->count())->toBe(1)
        ->and(Mailbox::query()->count())->toBe(3)
        ->and(AccountUsage::query()->firstOrFail()->warning_sent_at->equalTo($marked))->toBeTrue()
        ->and(Mailbox::query()->whereNull('warning_sent_at')->count())->toBe(0);
});

it('removes a mailbox the server no longer lists', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $state = fakeCpanelUsage();
    app(RefreshAccountUsage::class)->handle($server, 'logi');

    $remaining = json_decode(file_get_contents(base_path('tests/Fixtures/cpanel/list_pops_with_disk.json')), true);
    $remaining['data'] = [$remaining['data'][0]];
    $state['overrides'] = ['list_pops_with_disk' => $remaining];
    app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect(Mailbox::query()->pluck('address')->all())->toBe(['info@logi.com.tr']);
});

it('keeps the earlier values and records the failure when the server does not answer', function () {
    $server = Server::factory()->cpanel()->create(['username' => 'logi']);
    $state = fakeCpanelUsage();
    $this->travelTo('2026-10-21 09:00:00');
    app(RefreshAccountUsage::class)->handle($server, 'logi');

    $state['status'] = 401;
    $this->travelTo('2026-10-21 15:00:00');
    $usage = app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($usage->failure_reason)->toBe(RemoteServerErrorReason::Unauthorized)
        ->and($usage->failed_at->toDateTimeString())->toBe('2026-10-21 15:00:00')
        ->and($usage->refreshed_at->toDateTimeString())->toBe('2026-10-21 09:00:00')
        ->and($usage->resources)->toHaveCount(5)
        ->and(Mailbox::query()->count())->toBe(3);

    $state['status'] = 200;
    $this->travelTo('2026-10-21 21:00:00');
    $usage = app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($usage->failed_at)->toBeNull()
        ->and($usage->failure_reason)->toBeNull()
        ->and($usage->refreshed_at->toDateTimeString())->toBe('2026-10-21 21:00:00');
});

it('records a first failed read without any values', function () {
    Http::fake(['*' => Http::response('broken', 500)]);
    $server = Server::factory()->whm()->create();

    $usage = app(RefreshAccountUsage::class)->handle($server, 'logi');

    expect($usage->failure_reason)->toBe(RemoteServerErrorReason::InvalidResponse)
        ->and($usage->refreshed_at)->toBeNull()
        ->and($usage->resources)->toBeNull()
        ->and($usage->lastAttemptAt()->equalTo($usage->failed_at))->toBeTrue();
});
