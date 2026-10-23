<?php

use App\Actions\Quotes\RenderQuotePdf;
use App\Enums\SettingKey;
use App\Filament\Pages\ManageSettings;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    Storage::fake('local');
});

it('opens with the quote defaults and no company details', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Teklif geçerlilik süresi')
        ->assertSee('Firma bilgileri')
        ->assertSchemaStateSet([
            'quote_validity_days' => 15,
            'quote_bcc_email' => null,
            'company_name' => null,
            'company_address' => null,
            'company_phone' => null,
            'company_email' => null,
            'company_tax_office' => null,
            'company_tax_number' => null,
        ]);
});

it('saves the quote validity, the BCC address and the company details', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'quote_validity_days' => 30,
            'quote_bcc_email' => 'teklif@example.com',
            'company_name' => 'Örnek Bilişim Ltd.',
            'company_address' => "Merkez Mah.\nİstanbul",
            'company_phone' => '0111 000 00 00',
            'company_email' => 'info@ornek.test',
            'company_tax_office' => 'Kadıköy',
            'company_tax_number' => '1234567890',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Ayarlar kaydedildi.');

    $settings = app(Settings::class);

    expect($settings->quoteValidityDays())->toBe(30)
        ->and($settings->quoteBccEmail())->toBe('teklif@example.com')
        ->and($settings->companyDetails()['name'])->toBe('Örnek Bilişim Ltd.')
        ->and($settings->companyDetails()['tax_number'])->toBe('1234567890');

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'quote_validity_days' => 30,
            'quote_bcc_email' => 'teklif@example.com',
            'company_name' => 'Örnek Bilişim Ltd.',
            'company_address' => "Merkez Mah.\nİstanbul",
            'company_email' => 'info@ornek.test',
        ]);
});

it('clears a company field and the BCC address', function () {
    $settings = app(Settings::class);
    $settings->set(SettingKey::QuoteBccEmail, 'teklif@example.com');
    $settings->set(SettingKey::CompanyDetails, (string) json_encode(['name' => 'Örnek', 'phone' => '0212']));

    Livewire::test(ManageSettings::class)
        ->fillForm(['quote_bcc_email' => null, 'company_phone' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($settings->quoteBccEmail())->toBeNull()
        ->and($settings->companyDetails()['phone'])->toBeNull()
        ->and($settings->companyDetails()['name'])->toBe('Örnek');
});

it('shows form errors and saves nothing for invalid quote values', function (array $invalid, string $field) {
    Livewire::test(ManageSettings::class)
        ->fillForm($invalid)
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(app(Settings::class)->quoteValidityDays())->toBe(15)
        ->and(app(Settings::class)->companyDetails()['email'])->toBeNull();
})->with([
    'zero days' => [['quote_validity_days' => 0], 'quote_validity_days'],
    'too many days' => [['quote_validity_days' => 366], 'quote_validity_days'],
    'no days' => [['quote_validity_days' => null], 'quote_validity_days'],
    'bad BCC address' => [['quote_bcc_email' => 'gecersiz'], 'quote_bcc_email'],
    'bad company e-mail' => [['company_email' => 'gecersiz'], 'company_email'],
]);

describe('logo', function () {
    it('uploads the logo into the company directory of the local disk', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm(['company_logo' => UploadedFile::fake()->image('logo.png', 120, 60)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = app(Settings::class)->companyDetails()['logo_path'];

        expect($path)->toStartWith('company/')->toEndWith('.png');
        Storage::disk('local')->assertExists($path);
    });

    it('keeps the logo when the form is saved again without touching it', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm(['company_logo' => UploadedFile::fake()->image('logo.png')])
            ->call('save');

        $path = app(Settings::class)->companyDetails()['logo_path'];

        Livewire::test(ManageSettings::class)
            ->fillForm(['company_name' => 'Örnek'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(Settings::class)->companyDetails()['logo_path'])->toBe($path);
        Storage::disk('local')->assertExists($path);
    });

    it('deletes the file when the logo is removed', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm(['company_logo' => UploadedFile::fake()->image('logo.png')])
            ->call('save');

        $path = app(Settings::class)->companyDetails()['logo_path'];

        Livewire::test(ManageSettings::class)
            ->fillForm(['company_logo' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(Settings::class)->companyDetails()['logo_path'])->toBeNull();
        Storage::disk('local')->assertMissing($path);
    });

    it('refuses a file that is not a PNG, JPEG or GIF image', function (UploadedFile $file) {
        Livewire::test(ManageSettings::class)
            ->fillForm(['company_logo' => $file])
            ->call('save')
            ->assertHasFormErrors(['company_logo']);

        expect(app(Settings::class)->companyDetails()['logo_path'])->toBeNull();
    })->with([
        'a PDF' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        'a text file' => fn () => UploadedFile::fake()->create('logo.txt', 1, 'text/plain'),
        'an SVG' => fn () => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
    ]);

    it('refuses a logo over 1 MB', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm(['company_logo' => UploadedFile::fake()->image('buyuk.png')->size(2048)])
            ->call('save')
            ->assertHasFormErrors(['company_logo']);

        expect(app(Settings::class)->companyDetails()['logo_path'])->toBeNull();
    });

    it('prints the uploaded logo and the company details on the quote PDF', function () {
        Livewire::test(ManageSettings::class)
            ->fillForm([
                'company_name' => 'Örnek Bilişim Ltd.',
                'company_logo' => UploadedFile::fake()->image('logo.png', 80, 40),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->create();

        $html = app(RenderQuotePdf::class)->html($quote);

        expect($html)->toContain('data:image/png;base64,')
            ->toContain('Örnek Bilişim Ltd.')
            ->and(app(RenderQuotePdf::class)->handle($quote))->toStartWith('%PDF');
    });
});

it('saves the IBAN and shows it again', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['company_iban' => 'tr33 0006 1005 1978 6457 8413 26'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Ayarlar kaydedildi.');

    expect(app(Settings::class)->companyDetails()['iban'])->toBe('TR330006100519786457841326');

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet(['company_iban' => 'TR330006100519786457841326']);
});
