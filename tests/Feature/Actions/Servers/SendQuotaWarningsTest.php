<?php

use App\Actions\Servers\ListUsageAlerts;
use App\Actions\Servers\SendQuotaWarnings;
use App\Enums\SettingKey;
use App\Enums\UsageLevel;
use App\Mail\QuotaWarningMail;
use App\Models\AccountUsage;
use App\Models\Mailbox;
use App\Models\Server;
use App\Support\Settings;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
});

/**
 * A WHM account of a linked, active service with the given resources.
 *
 * @param  list<array{id: string, label: string, used: int, limit: int|null, is_bytes: bool}>|null  $resources
 */
function warnedAccount(?array $resources = null, string $username = 'logi'): AccountUsage
{
    $server = Server::query()->first() ?? Server::factory()->whm()->create();
    UsageScenario::linkedService($server, $username);

    return UsageScenario::usage($server, $username, $resources);
}

function sendQuotaWarnings(): int
{
    return app(SendQuotaWarnings::class)->handle();
}

it('e-mails everything that newly crossed the threshold in one message and marks it', function () {
    $this->travelTo('2026-10-21 10:00:00');
    $usage = warnedAccount([
        UsageScenario::resource('disk_usage', 92, 100, true),
        UsageScenario::resource('email_accounts', 10, 10),
        UsageScenario::resource('mysql_databases', 1, 10),
    ]);
    $mailbox = UsageScenario::mailbox($usage->server, 'logi', 'info@logi.com.tr', 99, 100);

    expect(sendQuotaWarnings())->toBe(3);

    Mail::assertSentCount(1);
    Mail::assertSent(QuotaWarningMail::class, fn (QuotaWarningMail $mail): bool => $mail->hasTo('sahip@example.com')
        && $mail->alerts->count() === 3
        && $mail->threshold === 90);

    $usage->refresh();
    $mailbox->refresh();

    expect($usage->warned_levels)->toEqual(['disk_usage' => 'nearly_full', 'email_accounts' => 'full'])
        ->and($usage->warning_sent_at->toDateTimeString())->toBe('2026-10-21 10:00:00')
        ->and($mailbox->warned_level)->toBe(UsageLevel::NearlyFull)
        ->and($mailbox->warning_sent_at->toDateTimeString())->toBe('2026-10-21 10:00:00');
});

it('does not e-mail the same excess twice', function () {
    warnedAccount();

    expect(sendQuotaWarnings())->toBe(1)
        ->and(sendQuotaWarnings())->toBe(0);

    Mail::assertSentCount(1);
});

it('sends nothing when nothing is over the threshold', function () {
    warnedAccount([UsageScenario::resource('disk_usage', 10, 100, true)]);

    expect(sendQuotaWarnings())->toBe(0);

    Mail::assertNothingSent();
});

it('announces only what is new when another resource crosses later', function () {
    $usage = warnedAccount([UsageScenario::resource('disk_usage', 92, 100, true), UsageScenario::resource('bandwidth', 10, 100, true)]);
    sendQuotaWarnings();

    $usage->update(['resources' => [UsageScenario::resource('disk_usage', 93, 100, true), UsageScenario::resource('bandwidth', 95, 100, true)]]);

    expect(sendQuotaWarnings())->toBe(1);

    Mail::assertSentCount(2);
    Mail::assertSent(QuotaWarningMail::class, fn (QuotaWarningMail $mail): bool => $mail->alerts->count() === 1 && $mail->alerts[0]->resourceId === 'bandwidth');

    expect($usage->refresh()->warned_levels)->toEqual(['disk_usage' => 'nearly_full', 'bandwidth' => 'nearly_full']);
});

it('announces a worse level again but not a lower one', function () {
    $usage = warnedAccount([UsageScenario::resource('disk_usage', 92, 100, true)]);
    sendQuotaWarnings();

    $usage->update(['resources' => [UsageScenario::resource('disk_usage', 100, 100, true)]]);
    expect(sendQuotaWarnings())->toBe(1)
        ->and($usage->refresh()->warned_levels)->toBe(['disk_usage' => 'full']);

    $usage->update(['resources' => [UsageScenario::resource('disk_usage', 95, 100, true)]]);
    expect(sendQuotaWarnings())->toBe(0)
        ->and($usage->refresh()->warned_levels)->toBe(['disk_usage' => 'nearly_full']);

    $usage->update(['resources' => [UsageScenario::resource('disk_usage', 100, 100, true)]]);
    expect(sendQuotaWarnings())->toBe(1);

    Mail::assertSentCount(3);
});

it('forgets a resource that fell back under the threshold and announces it as new when it returns', function () {
    $usage = warnedAccount([UsageScenario::resource('disk_usage', 92, 100, true)]);
    $mailbox = UsageScenario::mailbox($usage->server, 'logi', 'info@logi.com.tr', 95, 100);
    sendQuotaWarnings();

    $usage->update(['resources' => [UsageScenario::resource('disk_usage', 50, 100, true)]]);
    $mailbox->update(['used_bytes' => 10]);

    expect(sendQuotaWarnings())->toBe(0)
        ->and($usage->refresh()->warned_levels)->toBeNull()
        ->and($mailbox->refresh()->warned_level)->toBeNull();

    $usage->update(['resources' => [UsageScenario::resource('disk_usage', 91, 100, true)]]);
    $mailbox->update(['used_bytes' => 96]);

    expect(sendQuotaWarnings())->toBe(2);

    Mail::assertSentCount(2);
});

it('sends and marks nothing without a notification address', function () {
    $usage = warnedAccount();
    app(Settings::class)->set(SettingKey::NotificationEmail, null);

    expect(sendQuotaWarnings())->toBe(0)
        ->and($usage->refresh()->warned_levels)->toBeNull()
        ->and($usage->warning_sent_at)->toBeNull();

    Mail::assertNothingSent();

    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    expect(sendQuotaWarnings())->toBe(1);
});

it('marks nothing when sending fails, so the next run tries again', function () {
    $usage = warnedAccount();
    $mailbox = UsageScenario::mailbox($usage->server, 'logi', 'info@logi.com.tr', 99, 100);

    Mail::shouldReceive('to')->once()->andThrow(new TransportException('SMTP bağlantısı kurulamadı.'));

    expect(fn () => sendQuotaWarnings())->toThrow(TransportException::class)
        ->and($usage->refresh()->warned_levels)->toBeNull()
        ->and($usage->warning_sent_at)->toBeNull()
        ->and($mailbox->refresh()->warned_level)->toBeNull();

    Mail::swap(new MailManager(app()));
    Mail::fake();

    expect(sendQuotaWarnings())->toBe(2);

    Mail::assertSentCount(1);
});

it('does not warn about accounts no active service needs', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'iptal', ['status' => 'cancelled', 'cancelled_on' => now()->toDateString()]);
    UsageScenario::usage($server, 'iptal');

    expect(sendQuotaWarnings())->toBe(0)
        ->and(app(ListUsageAlerts::class)->handle())->toBeEmpty();

    Mail::assertNothingSent();
});

it('renders the customer, the item, the usage and the level in both parts of the e-mail', function () {
    $this->travelTo('2026-10-21 10:00:00');
    $usage = warnedAccount();
    UsageScenario::mailbox($usage->server, 'logi', 'info@logi.com.tr', 100, 100);
    $alerts = app(ListUsageAlerts::class)->handle();
    $customer = $alerts[0]->owners[0]['short_name'];

    $mail = new QuotaWarningMail($alerts, now(), 90);

    $mail->assertSeeInHtml($customer)
        ->assertSeeInHtml('info@logi.com.tr')
        ->assertSeeInHtml('Disk')
        ->assertSeeInHtml('4,7 GB / 5 GB')
        ->assertSeeInHtml('%94')
        ->assertSeeInHtml('Dolmak üzere')
        ->assertSeeInHtml('Dolu')
        ->assertSeeInText($customer)
        ->assertSeeInText('info@logi.com.tr | 100 B / 100 B (%100) | Dolu')
        ->assertSeeInText('Disk | 4,7 GB / 5 GB (%94) | Dolmak üzere');
    expect($mail->envelope()->subject)->toBe('Hosting kotası uyarısı (2) – 21.10.2026');
});

it('is run by the refresh command, which tells how many warnings went out', function () {
    warnedAccount();
    $this->artisan('app:refresh-server-usage')
        ->expectsOutputToContain('1 kota uyarısı gönderildi.')
        ->assertSuccessful();

    $this->artisan('app:refresh-server-usage')->assertSuccessful();

    Mail::assertSentCount(1);
    expect(Mailbox::query()->count())->toBe(0);
});
