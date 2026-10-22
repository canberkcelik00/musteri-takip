<?php

use App\Actions\Quotes\RenderQuotePdf;
use App\Enums\Currency;
use App\Enums\SettingKey;
use App\Models\CatalogService;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

const ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function renderedHtml(Quote $quote): string
{
    return app(RenderQuotePdf::class)->html($quote);
}

it('renders a valid PDF with the DejaVu font that carries Turkish letters', function () {
    $quote = Quote::factory()->create(['notes' => 'Çalışma şartları: ığüöç İĞÜŞÖÇ']);
    QuoteItem::factory()->for($quote)->create(['name' => 'Şirket e-postası', 'price' => 250000]);

    $pdf = app(RenderQuotePdf::class)->handle($quote);

    expect($pdf)->toStartWith('%PDF')
        ->and($pdf)->toContain('DejaVuSans')
        ->and(app(RenderQuotePdf::class)->filename($quote))->toBe($quote->number.'.pdf');
});

it('shows the number, the dates, the items and the total in Turkish', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-11-02 10:00', 'Europe/Istanbul'));
    $quote = Quote::factory()->create(['number' => 'TKL-2026-0042', 'valid_until' => '2026-11-17']);
    QuoteItem::factory()->for($quote)->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000]);
    QuoteItem::factory()->for($quote)->create(['name' => 'Alan adı', 'price' => 45050, 'sort_order' => 1]);

    $html = renderedHtml($quote);

    expect($html)->toContain('Teklif')
        ->toContain('TKL-2026-0042')
        ->toContain('02.11.2026')
        ->toContain('17.11.2026')
        ->toContain('Linux Hosting 1000 MB')
        ->toContain('1.500 TL')
        ->toContain('450,50 TL')
        ->toContain('1.950,50 TL');
});

it('lists the items in the PDF order', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['name' => 'İkinci kalem', 'sort_order' => 2]);
    QuoteItem::factory()->for($quote)->create(['name' => 'Birinci kalem', 'sort_order' => 1]);

    $html = renderedHtml($quote);

    expect(strpos($html, 'Birinci kalem'))->toBeLessThan(strpos($html, 'İkinci kalem'));
});

it('writes "Ücretsiz" for a free item and leaves it out of the total', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['name' => 'Ücretli hizmet', 'price' => 100000]);
    QuoteItem::factory()->for($quote)->free()->create(['name' => 'Hediye bakım', 'sort_order' => 1]);

    $html = renderedHtml($quote);

    expect($html)->toContain('Hediye bakım')
        ->toContain('Ücretsiz')
        ->toContain('1.000 TL')
        ->and(substr_count($html, 'TL'))->toBe(2);
});

it('writes "Ücretsiz" as the total of a quote with only free items', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->free()->create();

    expect(renderedHtml($quote))->toContain('Toplam')
        ->and(substr_count(renderedHtml($quote), 'Ücretsiz'))->toBe(2);
});

it('shows one total per currency without adding them together', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->for($quote)->create(['price' => 1200000, 'currency' => Currency::Try]);
    QuoteItem::factory()->for($quote)->create(['price' => 35000, 'currency' => Currency::Usd, 'sort_order' => 1]);

    $html = renderedHtml($quote);

    expect($html)->toContain('12.000 TL')
        ->toContain('350 USD');
});

it('lists the contents of a quoted package under its line', function () {
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => 400000]);
    $package->catalogServices()->attach([
        CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB'])->id,
        CatalogService::factory()->create(['name' => '.com.tr Alan Adı'])->id,
    ]);
    $quote = Quote::factory()->create();
    QuoteItem::factory()->forPackage()->for($quote)->create(['package_id' => $package->id]);

    $html = renderedHtml($quote);

    expect($html)->toContain('Kurumsal Paket')
        ->toContain('Linux Hosting 1000 MB')
        ->toContain('.com.tr Alan Adı')
        ->and(strpos($html, 'Kurumsal Paket'))->toBeLessThan(strpos($html, 'Linux Hosting 1000 MB'));
});

it('addresses a customer by title, primary contact and billing address', function () {
    $customer = Customer::factory()->create([
        'short_name' => 'Logi',
        'legal_title' => 'Logitech Bilgisayar Ltd. Şti.',
        'billing_address' => 'Atatürk Cad. No:1 Kadıköy',
    ]);
    Contact::factory()->for($customer)->create(['name' => 'Murat Kaya', 'email' => 'murat@example.com', 'is_primary' => true]);
    $quote = Quote::factory()->for($customer)->create();
    QuoteItem::factory()->for($quote)->create();

    $html = renderedHtml($quote);

    expect($html)->toContain('Logitech Bilgisayar Ltd. Şti.')
        ->toContain('Murat Kaya')
        ->toContain('Atatürk Cad. No:1 Kadıköy')
        ->toContain('murat@example.com');
});

it('falls back to the short name for a customer without a title', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => null]);
    $quote = Quote::factory()->for($customer)->create();

    expect(renderedHtml($quote))->toContain('Logi');
});

it('addresses a hand-written recipient', function () {
    $quote = Quote::factory()->manualRecipient()->create([
        'recipient_name' => 'Ali Veli',
        'recipient_company' => 'Veli Ltd.',
        'recipient_phone' => '0532 111 22 33',
        'recipient_email' => 'ali@example.com',
    ]);

    expect(renderedHtml($quote))->toContain('Ali Veli')
        ->toContain('Veli Ltd.')
        ->toContain('0532 111 22 33')
        ->toContain('ali@example.com');
});

it('prints the notes only when there are some', function () {
    $without = Quote::factory()->create(['notes' => null]);
    $with = Quote::factory()->create(['notes' => 'Fiyatlara KDV dahil değildir.']);

    expect(renderedHtml($without))->not->toContain('Notlar')
        ->and(renderedHtml($with))->toContain('Notlar')->toContain('Fiyatlara KDV dahil değildir.');
});

describe('company details', function () {
    it('prints the company details at the top', function () {
        app(Settings::class)->set(SettingKey::CompanyDetails, (string) json_encode([
            'name' => 'Örnek Bilişim Ltd.',
            'address' => "Merkez Mah. 1. Sk.\nİstanbul",
            'phone' => '0212 000 00 00',
            'email' => 'info@ornek.test',
            'tax_office' => 'Kadıköy',
            'tax_number' => '1234567890',
        ]));

        $html = renderedHtml(Quote::factory()->create());

        expect($html)->toContain('Örnek Bilişim Ltd.')
            ->toContain('Merkez Mah. 1. Sk.')
            ->toContain('İstanbul')
            ->toContain('0212 000 00 00')
            ->toContain('info@ornek.test')
            ->toContain('Kadıköy / 1234567890');
    });

    it('escapes the company details', function () {
        app(Settings::class)->set(SettingKey::CompanyDetails, (string) json_encode(['name' => '<script>x</script>', 'address' => '<b>x</b>']));

        $html = renderedHtml(Quote::factory()->create());

        expect($html)->not->toContain('<script>x</script>')
            ->not->toContain('<b>x</b>');
    });

    it('embeds the logo as a data URI and still makes the PDF', function () {
        Storage::fake('local');
        Storage::disk('local')->put('company/logo.png', base64_decode(ONE_PIXEL_PNG));
        app(Settings::class)->set(SettingKey::CompanyDetails, (string) json_encode(['name' => 'Örnek', 'logo_path' => 'company/logo.png']));
        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->create();

        expect(renderedHtml($quote))->toContain('data:image/png;base64,')
            ->and(app(RenderQuotePdf::class)->handle($quote))->toStartWith('%PDF');
    });

    it('makes the PDF without a logo or company details', function (?string $details) {
        Storage::fake('local');
        $details === null || app(Settings::class)->set(SettingKey::CompanyDetails, $details);
        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->create();

        expect(renderedHtml($quote))->not->toContain('<img')
            ->and(app(RenderQuotePdf::class)->handle($quote))->toStartWith('%PDF');
    })->with([
        'nothing stored' => [null],
        'logo file missing' => [fn () => json_encode(['logo_path' => 'company/yok.png'])],
    ]);

    it('ignores a logo that is not a supported image', function () {
        Storage::fake('local');
        Storage::disk('local')->put('company/logo.txt', 'not an image');
        app(Settings::class)->set(SettingKey::CompanyDetails, (string) json_encode(['logo_path' => 'company/logo.txt']));

        expect(renderedHtml(Quote::factory()->create()))->not->toContain('<img');
    });
});
