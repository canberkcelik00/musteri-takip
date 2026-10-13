<?php

use App\Actions\Digest\BuildDigest;
use App\Enums\Currency;
use App\Enums\DigestFrequency;
use App\Enums\SettingKey;
use App\Filament\Resources\Customers\CustomerResource;
use App\Mail\DigestMail;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentAllocation;
use App\Support\Settings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
    $this->travelTo('2026-10-15 08:00:00');
});

/**
 * The digest of the month up to 09:00 on 15.10.2026; the records are created at 08:00, before it.
 */
function digestMailFor(DigestFrequency $frequency = DigestFrequency::Monthly): DigestMail
{
    test()->travelTo('2026-10-15 09:00:00');
    $now = Carbon::now('Europe/Istanbul');
    $digest = app(BuildDigest::class)->handle($now->copy()->subMonth(), $now);

    return new DigestMail($digest, $frequency, $now);
}

it('has a subject by frequency', function (DigestFrequency $frequency, string $subject) {
    Charge::factory()->create(['due_date' => '2026-09-01']);

    digestMailFor($frequency)->assertHasSubject($subject);
})->with([
    'daily' => [DigestFrequency::Daily, 'Günlük özet – 15.10.2026'],
    'monthly' => [DigestFrequency::Monthly, 'Aylık özet – 15.10.2026'],
]);

it('shows the three sections in Turkish with a link to the customer card on every row', function () {
    $logi = Customer::factory()->create(['short_name' => 'Logi']);
    $acme = Customer::factory()->create(['short_name' => 'Acme']);
    $mavi = Customer::factory()->create(['short_name' => 'Mavi']);

    Charge::factory()->for($logi)->create([
        'description' => 'Hosting 2026–2027',
        'amount' => 400050,
        'currency' => Currency::Try,
        'due_date' => '2026-11-01',
    ]);
    CustomerService::factory()->for($acme)->free()->create([
        'name' => 'Alan adı',
        'start_date' => '2025-11-01',
        'end_date' => '2026-11-01',
    ]);
    $this->travelTo('2026-08-01 10:00:00');
    Charge::factory()->for($mavi)->create([
        'description' => 'SSL',
        'amount' => 35000,
        'currency' => Currency::Usd,
        'due_date' => '2026-09-01',
    ]);
    $this->travelTo('2026-10-15 08:00:00');

    $mail = digestMailFor();

    $mail->assertSeeInOrderInHtml([
        'Aylık özet – 15.10.2026',
        '1 yeni borç · 1 karar bekliyor · 1 gecikmiş',
        'Son özetten bu yana oluşan borçlar (1)',
        'Müşteri', 'Hizmet', 'Tutar', 'Vade',
        'Logi', 'Hosting 2026–2027', '4.000,50 TL', '01.11.2026',
        'Ücretsiz devam kararı bekleyenler (1)',
        'Yeni döneme ücretsiz devam edecek mi?',
        'Müşteri', 'Hizmet', 'Bitiş',
        'Acme', 'Alan adı', '01.11.2026',
        'Vadesi geçmiş borçlar (1)',
        'Müşteri', 'Hizmet', 'Kalan', 'Gecikme',
        'Mavi', 'SSL', 'Vade 01.09.2026', '350 USD', 'Gecikmiş (44 gün)',
        'Toplam: 350 USD',
        'Panele git',
    ]);

    foreach ([$logi, $acme, $mavi] as $customer) {
        $mail->assertSeeInHtml('href="'.CustomerResource::getUrl('view', ['record' => $customer], panel: 'panel').'"', false);
    }
});

it('leaves out empty sections', function () {
    Charge::factory()->create(['due_date' => '2026-09-01', 'created_at' => '2026-08-01 10:00:00']);

    $mail = digestMailFor();

    $mail->assertSeeInHtml('Vadesi geçmiş borçlar')
        ->assertDontSeeInHtml('Son özetten bu yana oluşan borçlar')
        ->assertDontSeeInHtml('Ücretsiz devam kararı bekleyenler');
});

it('shows a payment badge on a new charge and a note when it was partly paid from the credit balance', function () {
    $closed = Charge::factory()->paid()->create(['description' => 'Alan adı', 'amount' => 100000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->fromCreditBalance()->create(['charge_id' => $closed->id, 'amount' => 100000]);

    $partly = Charge::factory()->create(['description' => 'Hosting', 'amount' => 200000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->fromCreditBalance()->create(['charge_id' => $partly->id, 'amount' => 50000]);

    Charge::factory()->create(['description' => 'SSL', 'amount' => 40000, 'due_date' => '2026-12-01']);

    $mail = digestMailFor();

    $mail->assertSeeInOrderInHtml([
        'Alan adı', 'Bakiyeden kapatıldı',
        'Hosting', '500 TL alacak bakiyesinden düşüldü', 'Kalan 1.500 TL', 'Kısmi ödendi',
        'SSL',
    ]);
    expect(substr_count($mail->render(), 'badge-green'))->toBeGreaterThan(0)
        ->and(substr_count($mail->render(), 'Bakiyeden kapatıldı'))->toBe(1);
});

it('shows Ödendi on a new charge paid by a payment, not the credit balance badge', function () {
    $paid = Charge::factory()->paid()->create(['description' => 'Hosting', 'amount' => 100000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->create(['charge_id' => $paid->id, 'amount' => 100000]);

    digestMailFor()->assertSeeInHtml('Hosting')
        ->assertSeeInHtml('Ödendi')
        ->assertDontSeeInHtml('Bakiyeden kapatıldı')
        ->assertDontSeeInHtml('alacak bakiyesinden');
});

it('shows no badge on an open new charge, even when it is already overdue', function () {
    Charge::factory()->create(['description' => 'Gecikmiş yeni', 'amount' => 100000, 'due_date' => '2026-09-01']);

    $html = digestMailFor()->render();

    // The overdue section has one badge for it; the new-charges row has none.
    expect(substr_count($html, 'Gecikmiş (44 gün)'))->toBe(1)
        ->and($html)->not->toContain('Vadesi yaklaşıyor');
});

it('totals the overdue charges per currency without adding them together', function () {
    Charge::factory()->create(['amount' => 1000000, 'currency' => Currency::Try, 'due_date' => '2026-09-01', 'created_at' => '2026-08-01 10:00:00']);
    Charge::factory()->create(['amount' => 165000, 'currency' => Currency::Try, 'due_date' => '2026-09-02', 'created_at' => '2026-08-01 10:00:00']);
    Charge::factory()->create(['amount' => 5900, 'currency' => Currency::Usd, 'due_date' => '2026-09-06', 'created_at' => '2026-08-01 10:00:00']);

    digestMailFor()->assertSeeInHtml('Toplam: 11.650 TL + 59 USD');
});

it('puts the counts on one line and omits the empty sections from it', function () {
    Charge::factory()->create(['due_date' => '2026-09-01', 'created_at' => '2026-08-01 10:00:00']);

    digestMailFor()->assertSeeInHtml('1 gecikmiş')
        ->assertDontSeeInHtml('yeni borç')
        ->assertDontSeeInHtml('karar bekliyor');
});

it('has a Turkish footer and no English text of the framework', function () {
    Charge::factory()->create(['due_date' => '2026-09-01', 'created_at' => '2026-08-01 10:00:00']);

    $mail = digestMailFor();

    $mail->assertSeeInHtml('Tüm hakları saklıdır.')
        ->assertSeeInHtml('© 2026 Müşteri Takip')
        ->assertDontSeeInHtml('All rights reserved')
        ->assertDontSeeInHtml('Laravel');
    expect($mail->render())->not->toMatch('/\bHello\b|\bRegards\b|\bwhoops\b/i');
});

it('takes the panel colors, loads nothing from outside and keeps badges on one line', function () {
    Charge::factory()->create(['due_date' => '2026-09-01', 'created_at' => '2026-08-01 10:00:00']);

    $html = digestMailFor()->render();

    expect($html)->toContain('background-color: #1d3a6a')
        ->and($html)->toContain('width="600"')
        ->and($html)->toContain('white-space: nowrap')
        ->and($html)->toContain('prefers-color-scheme: dark')
        ->and($html)->toContain('max-width: 480px')
        ->and($html)->not->toMatch('/<img|<link|@import|url\(/i')
        ->and($html)->not->toMatch('/(src|href)="https?:\/\/(?!localhost)/i');
});

it('has a plain text version without HTML', function () {
    $this->travelTo('2026-08-01 10:00:00');
    Charge::factory()->for(Customer::factory()->create(['short_name' => 'Logi']))->create(['description' => 'SSL', 'amount' => 35000, 'currency' => Currency::Usd, 'due_date' => '2026-09-01']);
    $this->travelTo('2026-10-15 08:00:00');

    $mail = digestMailFor();

    $mail->assertSeeInText('Logi | SSL | Kalan 350 USD')
        ->assertSeeInText('Toplam: 350 USD')
        ->assertSeeInText('Panele git');
    expect($mail->render())->toBeString();
});

it('gives the columns fixed widths, with the customer column the widest of the short ones', function () {
    $this->travelTo('2026-10-15 08:00:00');
    Charge::factory()->create(['due_date' => '2026-12-01']);
    CustomerService::factory()->free()->create(['start_date' => '2025-11-01', 'end_date' => '2026-11-01']);
    Charge::factory()->create(['due_date' => '2026-09-01', 'created_at' => '2026-08-01 10:00:00']);

    $html = digestMailFor()->render();

    foreach ([30, 34, 18, 50, 20, 22] as $width) {
        expect($html)->toContain('width="'.$width.'%"')->and($html)->toContain('width: '.$width.'%;');
    }
});

it('writes the remaining amount note in the muted text color, not the link color', function () {
    $partly = Charge::factory()->create(['amount' => 200000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->create(['charge_id' => $partly->id, 'amount' => 50000]);

    $html = digestMailFor()->render();

    expect($html)->toMatch('/<span class="dg-note"[^>]*color: #566173;[^>]*>\s*Kalan 1\.500 TL/')
        ->and($html)->toContain('.dg-note')
        ->and($html)->toContain('color: #9aa5b5 !important');
});
