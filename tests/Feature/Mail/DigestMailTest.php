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
    $this->travelTo('2026-10-15 09:00:00');
});

function digestMailFor(DigestFrequency $frequency = DigestFrequency::Monthly): DigestMail
{
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
    $this->travelTo('2026-10-15 09:00:00');

    $mail = digestMailFor();

    $mail->assertSeeInOrderInHtml([
        'Aylık özet',
        'Son özetten bu yana oluşan borçlar (1)',
        'Logi', 'Hosting 2026–2027', '4.000,50 TL', 'vade 01.11.2026',
        'Ücretsiz devam kararı bekleyenler (1)',
        'Yeni döneme ücretsiz devam edecek mi?',
        'Acme', 'Alan adı', 'bitiş 01.11.2026',
        'Vadesi geçmiş borçlar (1)',
        'Mavi', 'SSL', 'kalan 350 USD', 'vade 01.09.2026', '44 gün gecikti',
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

it('says when a new charge was closed fully or partly from the credit balance', function () {
    $closed = Charge::factory()->paid()->create(['description' => 'Alan adı', 'amount' => 100000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->fromCreditBalance()->create(['charge_id' => $closed->id, 'amount' => 100000]);

    $partly = Charge::factory()->create(['description' => 'Hosting', 'amount' => 200000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->fromCreditBalance()->create(['charge_id' => $partly->id, 'amount' => 50000]);

    Charge::factory()->create(['description' => 'SSL', 'amount' => 40000, 'due_date' => '2026-12-01']);

    digestMailFor()->assertSeeInOrderInHtml([
        'Alan adı', 'alacak bakiyesinden kapatıldı',
        'Hosting', '500 TL alacak bakiyesinden düşüldü',
        'SSL',
    ]);
});

it('does not mark a charge paid by a payment as closed from the credit balance', function () {
    $paid = Charge::factory()->paid()->create(['description' => 'Hosting', 'amount' => 100000, 'due_date' => '2026-12-01']);
    PaymentAllocation::factory()->create(['charge_id' => $paid->id, 'amount' => 100000]);

    digestMailFor()->assertSeeInHtml('Hosting')
        ->assertDontSeeInHtml('alacak bakiyesinden');
});
