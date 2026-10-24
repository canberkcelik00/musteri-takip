<?php

use App\Actions\Digest\BuildDigest;
use App\Actions\Digest\SendDigest;
use App\Enums\DigestFrequency;
use App\Enums\DigestResult;
use App\Enums\SettingKey;
use App\Mail\DigestMail;
use App\Models\Server;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\UsageScenario;

beforeEach(function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
    $this->travelTo('2027-02-10 10:00:00');
});

function digestWithQuotaAlerts(): array
{
    $now = Carbon::now('Europe/Istanbul');

    return app(BuildDigest::class)->handle($now->copy()->subMonth(), $now);
}

it('lists the resources over the threshold, whether or not they were e-mailed before', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [
        UsageScenario::resource('disk_usage', 92, 100, true),
        UsageScenario::resource('bandwidth', 100, 100, true),
        UsageScenario::resource('email_accounts', 1, 10),
    ], ['warned_levels' => ['disk_usage' => 'nearly_full']]);

    $digest = digestWithQuotaAlerts();

    expect($digest['quota_alerts']->map(fn ($alert): string => $alert->subject)->all())->toBe(['Aylık trafik', 'Disk'])
        ->and(BuildDigest::isEmpty($digest))->toBeFalse();
});

it('is empty without alerts', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi', [UsageScenario::resource('disk_usage', 10, 100, true)]);

    $digest = digestWithQuotaAlerts();

    expect($digest['quota_alerts'])->toBeEmpty()
        ->and(BuildDigest::isEmpty($digest))->toBeTrue();
});

it('sends a digest that holds nothing but quota alerts', function () {
    $server = Server::factory()->whm()->create();
    UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi');
    app(Settings::class)->set(SettingKey::DigestFrequency, DigestFrequency::Daily->value);
    app(Settings::class)->set(SettingKey::DigestTime, '09:00');
    app(Settings::class)->markDigestRun(Carbon::parse('2027-02-09 09:00:00', 'Europe/Istanbul'));

    expect(app(SendDigest::class)->handle()['result'])->toBe(DigestResult::Sent);

    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->digest['quota_alerts']->count() === 1);
});

it('shows the quota section in both parts of the mail, with a link to the customer card', function () {
    $server = Server::factory()->whm()->create();
    $service = UsageScenario::linkedService($server, 'logi');
    UsageScenario::usage($server, 'logi');
    $now = Carbon::now('Europe/Istanbul');
    $mail = new DigestMail(digestWithQuotaAlerts(), DigestFrequency::Monthly, $now);
    $url = route('app.customers.show', $service->customer_id);

    $mail->assertSeeInOrderInHtml([
        '1 kota uyarısı',
        'Kotası dolmak üzere olanlar (1)',
        'Müşteri', 'Kalem', 'Kullanım', 'Durum',
        $service->customer->short_name, 'Disk', '4,7 GB / 5 GB', 'Dolmak üzere',
    ]);
    $mail->assertSeeInHtml('href="'.$url.'"', false);
    $mail->assertSeeInOrderInText([
        'Kotası dolmak üzere olanlar (1)',
        $service->customer->short_name.' | Disk | 4,7 GB / 5 GB (%94) | Dolmak üzere',
        $url,
    ]);
});

it('does not show the quota section when there is nothing to warn about', function () {
    $mail = new DigestMail(digestWithQuotaAlerts(), DigestFrequency::Monthly, Carbon::now('Europe/Istanbul'));

    $mail->assertDontSeeInHtml('Kotası dolmak üzere olanlar');
    $mail->assertDontSeeInText('Kotası dolmak üzere olanlar');
});
