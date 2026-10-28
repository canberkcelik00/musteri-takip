<?php

use App\Actions\Tasks\ParseQuickTask;
use App\Enums\ServiceType;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use Illuminate\Support\Facades\DB;

function parseQuickTask(string $message): array
{
    return app(ParseQuickTask::class)->handle($message);
}

function customerWithDomain(string $shortName, string $domain): Customer
{
    $customer = Customer::factory()->create(['short_name' => $shortName]);
    $service = CustomerService::factory()->for($customer)->create();

    CustomerServiceItem::factory()->for($service)->ofType(ServiceType::Domain)->create(['details' => ['domain' => $domain]]);

    return $customer;
}

describe('title and notes', function () {
    it('takes the first filled line as the title and keeps the whole message as the notes', function () {
        $result = parseQuickTask("\n  Ana sayfadaki   banner değişsin\nRengi mavi olsun\n");

        expect($result['title'])->toBe('Ana sayfadaki banner değişsin')
            ->and($result['notes'])->toBe("Ana sayfadaki   banner değişsin\nRengi mavi olsun");
    });

    it('leaves the notes empty when the message is one short line', function () {
        $result = parseQuickTask('Banner değişsin');

        expect($result['title'])->toBe('Banner değişsin')
            ->and($result['notes'])->toBeNull();
    });

    it('shortens a long title at a word boundary', function () {
        $result = parseQuickTask(str_repeat('kelime ', 30));

        expect(mb_strlen($result['title']))->toBeLessThanOrEqual(100)
            ->and($result['title'])->toEndWith('kelime…')
            ->and($result['notes'])->not->toBeNull();
    });

    it('drops both WhatsApp prefix formats from the title', function (string $line) {
        $result = parseQuickTask($line);

        expect($result['title'])->toBe('Footer yazısı değişsin')
            ->and($result['notes'])->toBe($line);
    })->with([
        'date first' => '[28.10.2026 14:32] Ahmet Yılmaz: Footer yazısı değişsin',
        'time first' => '[14:32, 28.10.2026] Ahmet Yılmaz: Footer yazısı değişsin',
    ]);

    it('cuts a message longer than 5000 characters', function () {
        $result = parseQuickTask(str_repeat('a ', 4000));

        expect(mb_strlen($result['notes']))->toBeLessThanOrEqual(5000);
    });

    it('returns an empty title for an empty or blank message', function (string $message) {
        expect(parseQuickTask($message))->toBe(['title' => '', 'notes' => null, 'customer_id' => null, 'matched_by' => null]);
    })->with(['empty' => '', 'blank' => " \n\t  \n"]);
});

describe('customer', function () {
    it('finds the customer by a domain name in the text', function () {
        $customer = customerWithDomain('Logi', 'logi.com.tr');
        customerWithDomain('Başka', 'baska.com');

        $result = parseQuickTask('https://www.logi.com.tr/hakkimizda sayfası değişsin');

        expect($result['customer_id'])->toBe($customer->id)
            ->and($result['matched_by'])->toBe('logi.com.tr');
    });

    it('finds the customer by a phone number however it is written', function (string $phone) {
        $customer = Customer::factory()->create(['short_name' => 'Telefoncu']);
        Contact::factory()->for($customer)->create(['phone' => '0532 123 45 67']);
        Contact::factory()->for(Customer::factory()->create())->create(['phone' => '0532 999 88 77']);

        $result = parseQuickTask("Bizi arayın $phone banner değişsin");

        expect($result['customer_id'])->toBe($customer->id)
            ->and($result['matched_by'])->toBe($phone);
    })->with(['+90 532 123 45 67', '0532 123 45 67', '5321234567', '0532-123-45-67']);

    it('finds the customer by the sender name of a contact', function () {
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->create(['name' => 'Ahmet Yılmaz']);

        $result = parseQuickTask('[28.10.2026 14:32] ahmet yılmaz: Footer değişsin');

        expect($result['customer_id'])->toBe($customer->id)
            ->and($result['matched_by'])->toBe('ahmet yılmaz');
    });

    it('finds the customer by the phone number a sender is saved under', function () {
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->create(['phone' => '0532 123 45 67']);

        $result = parseQuickTask('[28.10.2026 14:32] +90 532 123 45 67: Footer değişsin');

        expect($result['customer_id'])->toBe($customer->id);
    });

    it('finds the customer by its short name as a whole word', function () {
        $customer = Customer::factory()->create(['short_name' => 'Mavi Ofis']);
        Customer::factory()->create(['short_name' => 'Yeşil']);

        $result = parseQuickTask('Mavi Ofis sitesinde logo değişsin');

        expect($result['customer_id'])->toBe($customer->id)
            ->and($result['matched_by'])->toBe('Mavi Ofis');
    });

    it('does not match a short name inside another word', function () {
        Customer::factory()->create(['short_name' => 'Logi']);

        expect(parseQuickTask('Logistik raporu eklensin')['customer_id'])->toBeNull();
    });

    it('leaves the customer empty when the signs point to two customers', function () {
        customerWithDomain('Logi', 'logi.com.tr');
        customerWithDomain('Başka', 'baska.com');

        $result = parseQuickTask('logi.com.tr ile baska.com aynı olsun');

        expect($result['customer_id'])->toBeNull()
            ->and($result['matched_by'])->toBeNull();
    });

    it('selects the customer when several signs point to the same one', function () {
        $customer = customerWithDomain('Logi', 'logi.com.tr');
        Contact::factory()->for($customer)->create(['name' => 'Ahmet Yılmaz']);

        $result = parseQuickTask('[28.10.2026 14:32] Ahmet Yılmaz: logi.com.tr banner değişsin');

        expect($result['customer_id'])->toBe($customer->id)
            ->and($result['matched_by'])->toBe('logi.com.tr');
    });

    it('leaves the customer empty when nothing matches', function () {
        customerWithDomain('Logi', 'logi.com.tr');

        $result = parseQuickTask('Banner değişsin, yeni.example.org');

        expect($result['customer_id'])->toBeNull()
            ->and($result['matched_by'])->toBeNull();
    });

    it('never selects an archived customer', function () {
        $customer = customerWithDomain('Logi', 'logi.com.tr');
        $customer->archive();

        expect(parseQuickTask('logi.com.tr banner değişsin')['customer_id'])->toBeNull();
    });

    it('writes nothing', function () {
        customerWithDomain('Logi', 'logi.com.tr');
        $count = DB::table('tasks')->count();

        parseQuickTask('logi.com.tr banner değişsin');

        expect(DB::table('tasks')->count())->toBe($count);
    });
});
