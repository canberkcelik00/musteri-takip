<?php

use App\Actions\Quotes\AcceptQuote;
use App\Actions\Quotes\CreateQuote;
use App\Actions\Quotes\SendQuote;
use App\Enums\ChargeStatus;
use App\Enums\QuoteStatus;
use App\Enums\SettingKey;
use App\Livewire\Pages\Customers\Show;
use App\Mail\QuoteMail;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\QuoteItem;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo('2027-03-01 10:00:00');
});

it('takes a quote from the catalog to a customer card with its services and first charge', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    $hosting = CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000]);
    $package = Package::factory()->create(['name' => 'Kurumsal Paket']);

    $quote = app(CreateQuote::class)->handle($hosting, null, [
        'recipient_name' => 'Ali Veli',
        'recipient_company' => 'Veli Ltd.',
        'recipient_email' => 'ali@example.com',
    ]);
    QuoteItem::factory()->for($quote)->forPackage()->create(['package_id' => $package->id, 'sort_order' => 1]);
    QuoteItem::factory()->for($quote)->free()->create([
        'catalog_service_id' => $hosting->id,
        'name' => 'Ücretsiz kurulum',
        'sort_order' => 2,
    ]);

    expect($quote->number)->toBe('TKL-2027-0001')
        ->and($quote->status)->toBe(QuoteStatus::Draft);

    app(SendQuote::class)->handle($quote);

    Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('ali@example.com')
        && $mail->hasBcc('sahip@example.com')
        && $mail->hasSubject('Teklif TKL-2027-0001'));

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Sent)
        ->and($quote->sent_to)->toBe('ali@example.com');

    $accepted = app(AcceptQuote::class)->handle($quote);

    $customer = Customer::query()->sole();

    expect($accepted->status)->toBe(QuoteStatus::Accepted)
        ->and($accepted->customer_id)->toBe($customer->id)
        ->and($customer->primaryContact?->email)->toBe('ali@example.com')
        ->and(CustomerService::query()->where('customer_id', $customer->id)->count())->toBe(3);

    $charges = Charge::query()->where('customer_id', $customer->id)->get();

    expect($charges)->toHaveCount(2)
        ->and($charges->every(fn (Charge $charge): bool => $charge->status === ChargeStatus::Open))->toBeTrue()
        ->and($charges->pluck('amount')->contains(150000))->toBeTrue();

    Livewire::test(Show::class, ['customer' => $customer])
        ->assertSee('Linux Hosting 1000 MB')
        ->assertSee('Kurumsal Paket')
        ->assertSee('Ücretsiz kurulum')
        ->assertSee('TKL-2027-0001')
        ->assertSee('Kabul edildi');
});
