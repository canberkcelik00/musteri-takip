<?php

use App\Enums\Currency;
use App\Mail\QuoteMail;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteItem;

/**
 * The mail as the sender builds it: the quote loaded with its items and primary contact.
 */
function quoteMailFor(Quote $quote, ?string $replyTo = null, ?string $bcc = null): QuoteMail
{
    $quote->load(['customer.primaryContact', 'items']);

    return new QuoteMail($quote, '%PDF-1.7 sahte', $quote->number.'.pdf', $replyTo, $bcc);
}

it('has the quote number in the subject', function () {
    $quote = Quote::factory()->create(['number' => 'TKL-2027-0042']);

    quoteMailFor($quote)->assertHasSubject('Teklif TKL-2027-0042');
});

it('replies to the notification address and copies the BCC address', function () {
    $mail = quoteMailFor(Quote::factory()->create(), 'sahip@example.com', 'teklif@example.com');

    expect($mail->hasReplyTo('sahip@example.com'))->toBeTrue()
        ->and($mail->hasBcc('teklif@example.com'))->toBeTrue();
});

it('has no reply-to or copy without addresses', function () {
    $mail = quoteMailFor(Quote::factory()->create());

    expect($mail->replyTo)->toBeEmpty()
        ->and($mail->bcc)->toBeEmpty();
});

it('attaches the PDF with its file name', function () {
    $quote = Quote::factory()->create(['number' => 'TKL-2027-0042']);

    quoteMailFor($quote)->assertHasAttachedData('%PDF-1.7 sahte', 'TKL-2027-0042.pdf', ['mime' => 'application/pdf']);
});

it('greets the primary contact and tells the number, the validity and the total', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz']);
    $quote = Quote::factory()->for($customer)->create(['number' => 'TKL-2027-0042', 'valid_until' => '2027-02-25']);
    QuoteItem::factory()->for($quote)->create(['price' => 400050]);

    $mail = quoteMailFor($quote);

    $mail->assertSeeInOrderInHtml([
        'Sayın Ayşe Yılmaz,',
        'Ekte TKL-2027-0042 numaralı teklifimizi bulabilirsiniz. Teklif 25.02.2027 tarihine kadar geçerlidir.',
        'Teklif no', 'TKL-2027-0042',
        'Geçerlilik', '25.02.2027',
        'Toplam', '4.000,50 TL',
        'bu e-postayı yanıtlayabilirsiniz',
    ]);
    $mail->assertSeeInOrderInText([
        'Sayın Ayşe Yılmaz,',
        'Teklif no: TKL-2027-0042',
        'Geçerlilik: 25.02.2027',
        'Toplam: 4.000,50 TL',
    ]);
});

it('greets a hand-written recipient by name', function () {
    $quote = Quote::factory()->manualRecipient()->create(['recipient_name' => 'Ali Veli']);
    QuoteItem::factory()->for($quote)->create();

    quoteMailFor($quote)->assertSeeInHtml('Sayın Ali Veli,');
});

it('greets without a name when there is none', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->for($customer)->create();
    QuoteItem::factory()->for($quote)->create();

    $mail = quoteMailFor($quote);

    $mail->assertSeeInHtml('Merhaba,');
    $mail->assertDontSeeInHtml('Sayın');
});

it('shows one total per currency and "Ücretsiz" for a free quote', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['price' => 1200000]);
    QuoteItem::factory()->for($quote)->create(['price' => 35000, 'currency' => Currency::Usd, 'sort_order' => 1]);

    quoteMailFor($quote)->assertSeeInHtml('12.000 TL + 350 USD');

    $free = Quote::factory()->create();
    QuoteItem::factory()->for($free)->free()->create();

    quoteMailFor($free)->assertSeeInHtml('Ücretsiz');
});

it('does not link to the panel', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create();

    quoteMailFor($quote)->assertDontSeeInHtml('/panel');
});
