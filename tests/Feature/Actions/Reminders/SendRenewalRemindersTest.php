<?php

use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Actions\Reminders\SendRenewalReminders;
use App\Enums\CustomerServiceStatus;
use App\Enums\SettingKey;
use App\Mail\RenewalReminderMail;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, '1');
    $this->travelTo('2027-02-10 10:00:00');
});

function sendRenewalReminders(): array
{
    return app(SendRenewalReminders::class)->handle();
}

it('sends one reminder to the primary contact and records it', function () {
    $charge = renewalChargeToRemind();
    Contact::factory()->create(['customer_id' => $charge->customer_id, 'email' => 'diger@example.com']);

    expect(sendRenewalReminders())->toBe(['sent' => 1, 'skipped' => 0, 'failed' => 0]);

    Mail::assertSent(RenewalReminderMail::class, 1);
    Mail::assertSent(RenewalReminderMail::class, fn (RenewalReminderMail $mail): bool => $mail->hasTo('ayse@example.com') && $mail->charge->is($charge));
    expect($charge->fresh()->reminder_sent_at?->toDateTimeString())->toBe('2027-02-10 10:00:00');
});

it('does not send a second reminder on a repeated run', function () {
    renewalChargeToRemind();

    sendRenewalReminders();
    $this->travelTo('2027-02-11 10:00:00');

    expect(sendRenewalReminders())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 0]);
    Mail::assertSent(RenewalReminderMail::class, 1);
});

it('catches up after missed scheduler runs, before the due date', function () {
    $this->travelTo('2027-01-30 10:00:00');
    $customerService = CustomerService::factory()->create(['start_date' => '2026-03-01', 'end_date' => '2027-03-01']);
    Contact::factory()->primary()->create(['customer_id' => $customerService->customer_id, 'email' => 'ayse@example.com']);
    app(CreateDueRenewalCharges::class)->handle();

    $this->travelTo('2027-02-20 10:00:00');

    expect(sendRenewalReminders()['sent'])->toBe(1);
    expect(Charge::query()->where('customer_service_id', $customerService->id)->value('reminder_sent_at'))->not->toBeNull();
});

it('still sends a renewal charge on its due date', function () {
    renewalChargeToRemind();
    $this->travelTo('2027-03-01 10:00:00');

    expect(sendRenewalReminders()['sent'])->toBe(1);
});

it('sends nothing while the setting is off, and sends once it is turned on', function () {
    $charge = renewalChargeToRemind();
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, null);

    expect(sendRenewalReminders())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 0]);
    Mail::assertNothingSent();
    expect($charge->fresh()->reminder_sent_at)->toBeNull();

    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, '1');

    expect(sendRenewalReminders()['sent'])->toBe(1);
});

it('skips charges that are not worth a reminder', function (Closure $make) {
    $make();

    expect(sendRenewalReminders())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 0]);
    Mail::assertNothingSent();
})->with([
    'free service' => [fn () => renewalChargeToRemind(service: ['is_free' => true])],
    'cancelled service' => [fn () => renewalChargeToRemind(service: ['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2027-02-01'])],
    'cancelled charge' => [fn () => renewalChargeToRemind(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'İptal'])],
    'paid charge' => [fn () => renewalChargeToRemind(['status' => 'paid'])],
    'overdue charge' => [fn () => renewalChargeToRemind(['due_date' => '2027-02-09'])],
    'manual charge' => [fn () => Charge::factory()->create(['due_date' => '2027-03-01'])],
    'already reminded' => [fn () => renewalChargeToRemind(['reminder_sent_at' => now()->subDay()])],
    'archived customer' => [function () {
        $charge = renewalChargeToRemind();
        $charge->customer->forceFill(['archived_at' => now()])->save();
    }],
]);

it('does not remind the first period charge of an assigned service', function () {
    $this->travelTo('2027-02-10 10:00:00');
    $customer = Customer::factory()->create();
    Contact::factory()->primary()->create(['customer_id' => $customer->id, 'email' => 'ayse@example.com']);
    $customerService = app(AssignCustomerService::class)->handle($customer, CatalogService::factory()->create(), ['start_date' => '2027-02-20']);

    $charge = Charge::query()->where('customer_service_id', $customerService->id)->sole();

    expect($charge->is_renewal)->toBeFalse()
        ->and(sendRenewalReminders())->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 0]);
    Mail::assertNothingSent();
});

it('skips a missing or invalid primary address without recording, and sends once it is fixed', function (?string $email) {
    $charge = renewalChargeToRemind(contact: ['email' => $email]);

    expect(sendRenewalReminders())->toBe(['sent' => 0, 'skipped' => 1, 'failed' => 0]);
    Mail::assertNothingSent();
    expect($charge->fresh()->reminder_sent_at)->toBeNull();

    $charge->customer->primaryContact->update(['email' => 'duzgun@example.com']);

    expect(sendRenewalReminders()['sent'])->toBe(1);
    Mail::assertSent(RenewalReminderMail::class, fn (RenewalReminderMail $mail): bool => $mail->hasTo('duzgun@example.com'));
})->with([
    'no address' => [null],
    'blank address' => ['  '],
    'invalid address' => ['gecersiz'],
]);

it('records nothing when a send fails, keeps sending the others, and leaves it awaiting a retry', function () {
    Log::spy();
    $failing = renewalChargeToRemind();
    $working = renewalChargeToRemind();

    $calls = 0;
    Mail::shouldReceive('to')->twice()->andReturnUsing(function () use (&$calls) {
        if (++$calls === 1) {
            throw new TransportException('SMTP bağlantısı kurulamadı.');
        }

        return new class
        {
            public function send(mixed $mailable): void {}
        };
    });

    expect(sendRenewalReminders())->toBe(['sent' => 1, 'skipped' => 0, 'failed' => 1])
        ->and($failing->fresh()->reminder_sent_at)->toBeNull()
        ->and($working->fresh()->reminder_sent_at)->not->toBeNull()
        ->and(Charge::query()->awaitingReminder()->pluck('id')->all())->toBe([$failing->id]);
});
