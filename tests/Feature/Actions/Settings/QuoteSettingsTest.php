<?php

use App\Actions\Settings\UpdateSettings;
use App\Enums\SettingKey;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $data
 */
function saveQuoteSettings(array $data): void
{
    app(UpdateSettings::class)->handle([
        'renewal_lead_days' => 30,
        'digest_frequency' => 'monthly',
        'digest_day_of_month' => 15,
        'digest_time' => '09:00',
        'notification_email' => null,
        ...$data,
    ]);
}

it('stores the quote validity and the BCC address', function () {
    saveQuoteSettings(['quote_validity_days' => '30', 'quote_bcc_email' => ' teklif@example.com ']);

    $settings = app(Settings::class);

    expect($settings->quoteValidityDays())->toBe(30)
        ->and($settings->quoteBccEmail())->toBe('teklif@example.com');
});

it('removes the BCC address when it is blank', function () {
    saveQuoteSettings(['quote_bcc_email' => 'teklif@example.com']);
    saveQuoteSettings(['quote_bcc_email' => '  ']);

    expect(app(Settings::class)->quoteBccEmail())->toBeNull()
        ->and(Setting::query()->where('key', 'quote_bcc_email')->exists())->toBeFalse();
});

it('keeps the quote settings when they are not given', function () {
    saveQuoteSettings(['quote_validity_days' => '30', 'quote_bcc_email' => 'teklif@example.com']);
    saveQuoteSettings([]);

    $settings = app(Settings::class);

    expect($settings->quoteValidityDays())->toBe(30)
        ->and($settings->quoteBccEmail())->toBe('teklif@example.com');
});

it('rejects an invalid quote validity or BCC address and stores nothing', function (array $invalid) {
    saveQuoteSettings(['quote_validity_days' => '30']);

    expect(fn () => saveQuoteSettings([...$invalid, 'renewal_lead_days' => 99]))
        ->toThrow(ValidationException::class);

    expect(app(Settings::class)->quoteValidityDays())->toBe(30)
        ->and(app(Settings::class)->renewalLeadDays())->toBe(30);
})->with([
    'zero days' => [['quote_validity_days' => '0']],
    'too many days' => [['quote_validity_days' => '366']],
    'not a number' => [['quote_validity_days' => 'abc']],
    'bad address' => [['quote_bcc_email' => 'gecersiz']],
]);

describe('company details', function () {
    it('stores them as one setting', function () {
        saveQuoteSettings([
            'company_name' => ' Örnek Bilişim Ltd. ',
            'company_address' => "Merkez Mah.\nİstanbul",
            'company_phone' => '0212 000 00 00',
            'company_email' => 'info@ornek.test',
            'company_tax_office' => 'Kadıköy',
            'company_tax_number' => '1234567890',
            'company_logo' => 'company/abc123.png',
        ]);

        expect(app(Settings::class)->companyDetails())->toBe([
            'name' => 'Örnek Bilişim Ltd.',
            'address' => "Merkez Mah.\nİstanbul",
            'phone' => '0212 000 00 00',
            'email' => 'info@ornek.test',
            'tax_office' => 'Kadıköy',
            'tax_number' => '1234567890',
            'logo_path' => 'company/abc123.png',
        ]);
    });

    it('keeps the fields that are not given', function () {
        saveQuoteSettings(['company_name' => 'Örnek', 'company_phone' => '0212']);
        saveQuoteSettings(['company_name' => 'Yeni ad']);

        $details = app(Settings::class)->companyDetails();

        expect($details['name'])->toBe('Yeni ad')
            ->and($details['phone'])->toBe('0212');

        saveQuoteSettings([]);

        expect(app(Settings::class)->companyDetails()['name'])->toBe('Yeni ad');
    });

    it('removes a blank field and the whole setting when nothing is left', function () {
        saveQuoteSettings(['company_name' => 'Örnek', 'company_phone' => '0212']);
        saveQuoteSettings(['company_phone' => '']);

        expect(app(Settings::class)->companyDetails()['phone'])->toBeNull();

        saveQuoteSettings(['company_name' => null]);

        expect(Setting::query()->where('key', 'company_details')->exists())->toBeFalse();
    });

    it('rejects an invalid e-mail', function () {
        expect(fn () => saveQuoteSettings(['company_email' => 'gecersiz']))->toThrow(ValidationException::class);
    });

    it('takes the logo from a one-item list', function () {
        saveQuoteSettings(['company_logo' => ['x1' => 'company/abc123.png']]);

        expect(app(Settings::class)->companyDetails()['logo_path'])->toBe('company/abc123.png');
    });

    it('only accepts a logo path inside the company directory', function (string $path) {
        expect(fn () => saveQuoteSettings(['company_logo' => $path]))->toThrow(ValidationException::class);

        expect(app(Settings::class)->companyDetails()['logo_path'])->toBeNull();
    })->with(['../.env', 'company/../../.env', '/etc/passwd', 'baska/logo.png', 'company/alt/logo.png', 'logo.png']);

    it('deletes the old logo file when the logo is replaced or removed', function () {
        Storage::fake('local');
        Storage::disk('local')->put('company/eski.png', 'a');
        Storage::disk('local')->put('company/yeni.png', 'b');

        saveQuoteSettings(['company_logo' => 'company/eski.png']);
        saveQuoteSettings(['company_logo' => 'company/yeni.png']);

        Storage::disk('local')->assertMissing('company/eski.png');
        Storage::disk('local')->assertExists('company/yeni.png');

        saveQuoteSettings(['company_logo' => null]);

        Storage::disk('local')->assertMissing('company/yeni.png');
        expect(app(Settings::class)->companyDetails()['logo_path'])->toBeNull();
    });

    it('keeps the logo file when the same logo is saved again or the logo is not given', function () {
        Storage::fake('local');
        Storage::disk('local')->put('company/logo.png', 'a');

        saveQuoteSettings(['company_logo' => 'company/logo.png']);
        saveQuoteSettings(['company_logo' => 'company/logo.png', 'company_name' => 'Örnek']);
        saveQuoteSettings(['company_name' => 'Başka']);

        Storage::disk('local')->assertExists('company/logo.png');
        expect(app(Settings::class)->companyDetails()['logo_path'])->toBe('company/logo.png');
    });

    it('never deletes a file outside the company directory, even when a stored path says so', function () {
        Storage::fake('local');
        Storage::disk('local')->put('onemli.txt', 'x');
        app(Settings::class)->set(SettingKey::CompanyDetails, (string) json_encode(['logo_path' => 'onemli.txt']));

        saveQuoteSettings(['company_logo' => null]);

        Storage::disk('local')->assertExists('onemli.txt');
    });
});
