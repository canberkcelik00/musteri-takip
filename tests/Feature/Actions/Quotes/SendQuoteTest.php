<?php

use App\Actions\Quotes\SendQuote;
use App\Enums\QuoteErrorReason;
use App\Enums\QuoteStatus;
use App\Enums\SettingKey;
use App\Exceptions\InvalidQuote;
use App\Mail\QuoteMail;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2027-02-10 10:00:00');
});

/**
 * A draft quote for a customer whose primary contact has an e-mail, with one item.
 *
 * @param  array<string, mixed>  $attributes
 */
function draftQuoteToSend(array $attributes = []): Quote
{
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com']);

    $quote = Quote::factory()->for($customer)->create([
        'valid_until' => '2027-02-20',
        ...$attributes,
    ]);
    QuoteItem::factory()->for($quote)->create(['price' => 400000]);

    return $quote;
}

it('sends the quote to the primary contact with the PDF and records the send', function () {
    $quote = draftQuoteToSend();

    $sent = app(SendQuote::class)->handle($quote);

    Mail::assertSent(QuoteMail::class, 1);
    Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('ayse@example.com')
        && $mail->quote->is($quote)
        && str_starts_with($mail->pdf, '%PDF')
        && $mail->filename === $quote->number.'.pdf');
    expect($sent->status)->toBe(QuoteStatus::Sent)
        ->and($sent->sent_at?->toDateTimeString())->toBe('2027-02-10 10:00:00')
        ->and($sent->sent_to)->toBe('ayse@example.com')
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('sends to a hand-written recipient', function () {
    $quote = Quote::factory()->manualRecipient()->create(['recipient_email' => 'ali@example.com', 'valid_until' => '2027-02-20']);
    QuoteItem::factory()->for($quote)->create();

    app(SendQuote::class)->handle($quote);

    Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('ali@example.com'));
    expect($quote->fresh()->sent_to)->toBe('ali@example.com');
});

it('sends to the given address instead of the default', function () {
    $quote = draftQuoteToSend();

    app(SendQuote::class)->handle($quote, ' muhasebe@example.com ');

    Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('muhasebe@example.com') && ! $mail->hasTo('ayse@example.com'));
    expect($quote->fresh()->sent_to)->toBe('muhasebe@example.com');
});

it('needs an address when the contact has none', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['email' => null]);
    $quote = Quote::factory()->for($customer)->create();
    QuoteItem::factory()->for($quote)->create();

    expect(fn () => app(SendQuote::class)->handle($quote))->toThrow(InvalidQuote::class, __('quotes.no_recipient'));

    Mail::assertNothingSent();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);

    app(SendQuote::class)->handle($quote, 'elle@example.com');

    Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('elle@example.com'));
});

it('rejects an invalid address', function () {
    $quote = draftQuoteToSend();
    $thrown = null;

    try {
        app(SendQuote::class)->handle($quote, 'gecersiz');
    } catch (InvalidQuote $exception) {
        $thrown = $exception;
    }

    expect($thrown?->reason)->toBe(QuoteErrorReason::RecipientEmailInvalid);
    Mail::assertNothingSent();
});

describe('copy and reply', function () {
    it('copies the quote BCC address and replies to the notification address', function () {
        app(Settings::class)->set(SettingKey::QuoteBccEmail, 'teklif@example.com');
        app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

        app(SendQuote::class)->handle(draftQuoteToSend());

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasBcc('teklif@example.com') && $mail->hasReplyTo('sahip@example.com'));
    });

    it('copies the notification address when no BCC address is set', function () {
        app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

        app(SendQuote::class)->handle(draftQuoteToSend());

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasBcc('sahip@example.com'));
    });

    it('sends no copy when neither address is set', function () {
        app(SendQuote::class)->handle(draftQuoteToSend());

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->bcc === [] && $mail->replyTo === []);
    });

    it('does not copy the recipient to itself', function () {
        app(Settings::class)->set(SettingKey::QuoteBccEmail, 'ayse@example.com');

        app(SendQuote::class)->handle(draftQuoteToSend());

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->bcc === []);
    });
});

describe('validity', function () {
    it('keeps the validity of a draft that is still valid', function () {
        $quote = draftQuoteToSend(['valid_until' => '2027-02-20']);

        app(SendQuote::class)->handle($quote);

        expect($quote->fresh()->valid_until->toDateString())->toBe('2027-02-20');
    });

    it('starts the validity again when a draft is sent after it ran out', function () {
        $quote = draftQuoteToSend(['valid_until' => '2027-02-09']);

        app(SendQuote::class)->handle($quote);

        expect($quote->fresh()->valid_until->toDateString())->toBe('2027-02-25');
    });

    it('starts the validity again from the settings when a sent quote is sent again', function () {
        app(Settings::class)->set(SettingKey::QuoteValidityDays, '30');
        $quote = draftQuoteToSend(['valid_until' => '2027-02-20']);
        app(SendQuote::class)->handle($quote);
        $this->travelTo('2027-02-15 09:00:00');

        app(SendQuote::class)->handle($quote->fresh());

        expect($quote->fresh()->valid_until->toDateString())->toBe('2027-03-17')
            ->and($quote->fresh()->sent_at?->toDateTimeString())->toBe('2027-02-15 09:00:00');
        Mail::assertSent(QuoteMail::class, 2);
    });

    it('makes an expired quote valid again with the same content', function () {
        $quote = Quote::factory()->expired()->for(Customer::factory()->create())->create(['valid_until' => '2027-02-01']);
        Contact::factory()->for($quote->customer)->primary()->create(['email' => 'ayse@example.com']);
        $item = QuoteItem::factory()->for($quote)->create(['name' => 'Linux Hosting', 'price' => 150000]);
        expect($quote->displayStatus()->value)->toBe('expired');

        $sent = app(SendQuote::class)->handle($quote);

        expect($sent->isExpired())->toBeFalse()
            ->and($sent->valid_until->toDateString())->toBe('2027-02-25')
            ->and($sent->status)->toBe(QuoteStatus::Sent)
            ->and($item->fresh()->name)->toBe('Linux Hosting')
            ->and($item->fresh()->price)->toBe(150000)
            ->and($sent->items)->toHaveCount(1);
    });

    it('sends the new validity in the mail', function () {
        $quote = draftQuoteToSend(['valid_until' => '2027-02-09']);

        app(SendQuote::class)->handle($quote);

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->quote->valid_until->toDateString() === '2027-02-25');
    });
});

it('cannot send an accepted or rejected quote', function (string $state) {
    $quote = Quote::factory()->{$state}()->for(Customer::factory()->create())->create();
    QuoteItem::factory()->for($quote)->create();

    expect(fn () => app(SendQuote::class)->handle($quote, 'ayse@example.com'))
        ->toThrow(InvalidQuote::class, __('quotes.not_sendable'));

    Mail::assertNothingSent();
})->with(['accepted', 'rejected']);

it('cannot send a quote without items', function () {
    $quote = Quote::factory()->for(Customer::factory()->create())->create();

    expect(fn () => app(SendQuote::class)->handle($quote, 'ayse@example.com'))
        ->toThrow(InvalidQuote::class, __('quotes.item_required'));
});

it('does not send to an archived customer', function () {
    $quote = draftQuoteToSend();
    $quote->customer->forceFill(['archived_at' => now()])->save();

    expect(fn () => app(SendQuote::class)->handle($quote))->toThrow(InvalidQuote::class, __('quotes.customer_archived'));

    Mail::assertNothingSent();
});

it('records nothing and keeps the draft when the send fails', function () {
    $quote = draftQuoteToSend(['valid_until' => '2027-02-09']);
    Mail::shouldReceive('to')->andThrow(new TransportException('SMTP kapalı'));

    expect(fn () => app(SendQuote::class)->handle($quote))->toThrow(TransportException::class);

    $fresh = $quote->fresh();

    expect($fresh->status)->toBe(QuoteStatus::Draft)
        ->and($fresh->sent_at)->toBeNull()
        ->and($fresh->sent_to)->toBeNull()
        ->and($fresh->valid_until->toDateString())->toBe('2027-02-09')
        ->and($quote->valid_until->toDateString())->toBe('2027-02-09')
        ->and($quote->isDirty('valid_until'))->toBeFalse();
});

it('leaves a quote accepted while the mail went out as it is', function () {
    $quote = draftQuoteToSend();
    $quote->update(['status' => QuoteStatus::Sent]);
    Mail::shouldReceive('to')->andReturnUsing(function () use ($quote) {
        Quote::query()->whereKey($quote->id)->update(['status' => QuoteStatus::Accepted]);

        return new class
        {
            public function send(): void {}
        };
    });

    app(SendQuote::class)->handle($quote);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('names the default recipient', function () {
    expect(SendQuote::defaultRecipient(draftQuoteToSend()))->toBe('ayse@example.com')
        ->and(SendQuote::defaultRecipient(Quote::factory()->manualRecipient()->create(['recipient_email' => 'ali@example.com'])))->toBe('ali@example.com')
        ->and(SendQuote::defaultRecipient(Quote::factory()->for(Customer::factory()->create())->create()))->toBeNull();
});
