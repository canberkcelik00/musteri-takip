<?php

use App\Mail\RenewalReminderMail;
use App\Models\Charge;
use App\Models\PaymentAllocation;

beforeEach(function () {
    $this->travelTo('2027-02-10 10:00:00');
});

/**
 * The mail as the sender builds it: the charge loaded with its contact, service and allocations sum.
 */
function renewalReminderMailFor(Charge $charge, ?string $replyTo = 'sahip@example.com'): RenewalReminderMail
{
    $charge = Charge::query()
        ->with(['customer.primaryContact', 'customerService'])
        ->withSum('allocations', 'amount')
        ->findOrFail($charge->id);

    return new RenewalReminderMail($charge, $replyTo);
}

it('has the service name in the subject and replies to the notification address', function () {
    $mail = renewalReminderMailFor(renewalChargeToRemind());

    $mail->assertHasSubject('Yenileme hatırlatması – Kurumsal Paket');
    expect($mail->hasReplyTo('sahip@example.com'))->toBeTrue();
});

it('has no reply-to without a notification address', function () {
    $mail = renewalReminderMailFor(renewalChargeToRemind(), replyTo: null);

    expect($mail->replyTo)->toBeEmpty();
});

it('tells the contact the end date, the new period, the remaining amount and the last payment date', function () {
    $mail = renewalReminderMailFor(renewalChargeToRemind(['amount' => 400050]));

    $mail->assertSeeInOrderInHtml([
        'Sayın Ayşe Yılmaz,',
        'Kurumsal Paket hizmetiniz 01.03.2027 tarihinde sona eriyor.',
        'Hizmet', 'Kurumsal Paket',
        'Yeni dönem', '01.03.2027 – 01.03.2028',
        'Tutar', '4.000,50 TL',
        'Son ödeme', '01.03.2027',
        'bu e-postayı yanıtlayabilirsiniz',
    ]);
    $mail->assertSeeInOrderInText([
        'Sayın Ayşe Yılmaz,',
        'Kurumsal Paket hizmetiniz 01.03.2027 tarihinde sona eriyor.',
        'Yeni dönem: 01.03.2027 – 01.03.2028',
        'Tutar: 4.000,50 TL',
        'Son ödeme: 01.03.2027',
    ]);
});

it('shows the remaining amount when part of the charge is already paid', function () {
    $charge = renewalChargeToRemind(['amount' => 400000]);
    PaymentAllocation::factory()->create(['charge_id' => $charge->id, 'amount' => 100000]);

    renewalReminderMailFor($charge)->assertSeeInHtml('3.000 TL');
});

it('does not link to the panel', function () {
    renewalReminderMailFor(renewalChargeToRemind())->assertDontSeeInHtml('/panel');
});
