<?php

use App\Actions\Reminders\BuildWhatsAppReminder;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Enums\SettingKey;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\PaymentAllocation;
use App\Support\Settings;

beforeEach(function () {
    $this->travelTo('2027-03-10 10:00:00');
});

/**
 * An open charge of a customer whose primary contact is "Ali Veli" with the given phone.
 *
 * @param  array<string, mixed>  $attributes
 */
function whatsAppCharge(array $attributes = [], ?string $phone = '0111 111 11 11'): Charge
{
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ali Veli', 'phone' => $phone]);

    return Charge::factory()->for($customer)->create([
        'description' => 'Linux Hosting 2026–2027',
        'amount' => 150000,
        'due_date' => '2027-03-15',
        ...$attributes,
    ]);
}

/**
 * The reminder text of the charge's link, decoded.
 */
function whatsAppText(Charge $charge): string
{
    $link = app(BuildWhatsAppReminder::class)->handle($charge);
    parse_str((string) parse_url((string) $link, PHP_URL_QUERY), $query);

    return $query['text'];
}

it('turns phone numbers into the international form WhatsApp links need', function (?string $phone, ?string $expected) {
    expect(BuildWhatsAppReminder::normalizePhone($phone))->toBe($expected);
})->with([
    'with the leading 0 and spaces' => ['0111 111 11 11', '901111111111'],
    'with brackets and a dash' => ['(111) 111-1111', '901111111111'],
    'with +90' => ['+90 111 111 11 11', '901111111111'],
    'with 90 and no plus' => ['901111111111', '901111111111'],
    'with 0090' => ['0090 111 111 11 11', '901111111111'],
    'ten digits only' => ['1111111111', '901111111111'],
    'a landline' => ['0111 000 00 00', '901110000000'],
    'a foreign number with plus' => ['+44 20 7946 0958', '442079460958'],
    'a foreign number with 00' => ['0044 20 7946 0958', '442079460958'],
    'too short' => ['123', null],
    'letters only' => ['yok', null],
    'a short foreign number' => ['+44 123', null],
    'eleven digits without a leading 0' => ['11111111111', null],
    'empty' => ['', null],
    'null' => [null, null],
]);

it('links to the chat of the primary contact with the message in the text', function () {
    $link = app(BuildWhatsAppReminder::class)->handle(whatsAppCharge());

    expect($link)->toStartWith('https://wa.me/901111111111?text=')
        ->and($link)->not->toContain(' ');
});

it('says the payment is overdue after the due date', function () {
    $charge = whatsAppCharge(['due_date' => '2027-03-09']);

    expect(whatsAppText($charge))->toBe('Merhaba Ali Veli, Linux Hosting 2026–2027 için 1.500 TL tutarındaki ödemenizin vadesi 09.03.2027 tarihinde geçmiştir. İyi çalışmalar.');
});

it('gives the last day for a charge that is not due yet or is due today', function () {
    expect(whatsAppText(whatsAppCharge(['due_date' => '2027-03-15'])))
        ->toBe('Merhaba Ali Veli, Linux Hosting 2026–2027 için 1.500 TL tutarındaki ödemenizin son günü 15.03.2027. İyi çalışmalar.')
        ->and(whatsAppText(whatsAppCharge(['due_date' => '2027-03-10'])))->toContain('son günü 10.03.2027');
});

it('asks for the remaining amount of a partially paid charge', function () {
    $charge = whatsAppCharge();
    PaymentAllocation::factory()->for($charge)->create(['amount' => 40000]);

    expect(whatsAppText($charge->fresh()))->toContain('1.100 TL tutarındaki');
});

it('writes the amount in the charge currency', function () {
    expect(whatsAppText(whatsAppCharge(['currency' => Currency::Usd, 'amount' => 25050])))->toContain('250,50 USD');
});

it('puts the first domain of the linked service before the description', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ali Veli', 'phone' => '0111 111 11 11']);
    $service = CustomerService::factory()->for($customer)->forCatalogService()->create();
    $service->items()->delete();
    CustomerServiceItem::factory()->for($service)->ofType(ServiceType::Hosting)->create(['details' => ['domain' => 'logi.com.tr']]);
    $charge = Charge::factory()->forCustomerService($service)->create(['description' => 'Linux Hosting 2026–2027', 'due_date' => '2027-03-15']);

    expect(whatsAppText($charge))->toContain('logi.com.tr – Linux Hosting 2026–2027 için');
});

it('adds the company name and the IBAN in groups of four', function () {
    app(Settings::class)->set(SettingKey::CompanyDetails, json_encode(['name' => 'Örnek Bilişim', 'iban' => 'TR330006100519786457841326']));

    expect(whatsAppText(whatsAppCharge()))->toBe('Merhaba Ali Veli, Linux Hosting 2026–2027 için 1.500 TL tutarındaki ödemenizin son günü 15.03.2027. Ödemeyi Örnek Bilişim adına TR33 0006 1005 1978 6457 8413 26 IBAN’ına yapabilirsiniz. İyi çalışmalar. Örnek Bilişim');
});

it('adds the IBAN without a company name, and the name without an IBAN', function () {
    $settings = app(Settings::class);

    $settings->set(SettingKey::CompanyDetails, json_encode(['iban' => 'TR330006100519786457841326']));
    expect(whatsAppText(whatsAppCharge()))->toContain('Ödemeyi TR33 0006 1005 1978 6457 8413 26 IBAN’ına yapabilirsiniz. İyi çalışmalar.');

    $settings->set(SettingKey::CompanyDetails, json_encode(['name' => 'Örnek Bilişim']));
    expect(whatsAppText(whatsAppCharge()))->not->toContain('IBAN')
        ->and(whatsAppText(whatsAppCharge()))->toEndWith('İyi çalışmalar. Örnek Bilişim');
});

it('encodes Turkish letters and special characters in the link', function () {
    $charge = whatsAppCharge(['description' => 'Alan adı & e-posta']);

    expect(app(BuildWhatsAppReminder::class)->handle($charge))->toContain('Alan%20ad%C4%B1%20%26%20e-posta');
});

it('offers no link without a usable phone number', function (?string $phone) {
    expect(app(BuildWhatsAppReminder::class)->handle(whatsAppCharge([], $phone)))->toBeNull();
})->with([[null], [''], ['123']]);

it('offers no link when the customer has no primary contact', function () {
    $charge = Charge::factory()->for(Customer::factory()->create())->create();
    Contact::factory()->for($charge->customer)->create(['phone' => '0111 111 11 11']);

    expect(app(BuildWhatsAppReminder::class)->handle($charge))->toBeNull();
});

it('offers no link for a charge that is not open or a customer that is archived', function () {
    $paid = whatsAppCharge(['status' => ChargeStatus::Paid]);
    $cancelled = whatsAppCharge(['status' => ChargeStatus::Cancelled]);
    $archived = whatsAppCharge();
    $archived->customer->archive();

    $builder = app(BuildWhatsAppReminder::class);

    expect($builder->handle($paid))->toBeNull()
        ->and($builder->handle($cancelled))->toBeNull()
        ->and($builder->handle($archived->fresh()))->toBeNull();
});
