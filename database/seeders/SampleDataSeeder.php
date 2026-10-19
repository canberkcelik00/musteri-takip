<?php

namespace Database\Seeders;

use App\Actions\Charges\CancelCharge;
use App\Actions\Contacts\CreateContact;
use App\Actions\Customers\ArchiveCustomer;
use App\Actions\CustomerServices\AdvanceCustomerServicePeriod;
use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Realistic Turkish sample data for local use. All dates are relative to today, so
 * paid, partially paid, overdue and upcoming charges stay so whenever it is run.
 *
 * Services, charges and payments are created by the Phase 3 Actions, each at the date
 * it happened: the clock is set to that date for the call and set back afterwards, so
 * the data follows the same rules as the application (first period charge, allocation,
 * credit balance, renewal). The clock the seeder was started with is restored at the end.
 */
class SampleDataSeeder extends Seeder
{
    private CarbonImmutable $now;

    private CarbonImmutable $today;

    /** @var array<string, CatalogService> */
    private array $catalog = [];

    /** @var array<string, Package> */
    private array $packages = [];

    public function __construct(
        private CreateContact $createContact,
        private AssignCustomerService $assignCustomerService,
        private RecordPayment $recordPayment,
        private CreateRenewalCharge $createRenewalCharge,
        private AdvanceCustomerServicePeriod $advanceCustomerServicePeriod,
        private CancelCustomerService $cancelCustomerService,
        private CancelCharge $cancelCharge,
        private ArchiveCustomer $archiveCustomer,
    ) {}

    public function run(): void
    {
        $previousTestNow = Carbon::getTestNow();

        $this->now = CarbonImmutable::now(config('app.timezone'));
        $this->today = $this->now->startOfDay();

        try {
            Carbon::setTestNow($this->now);

            $this->seedCatalog();
            $this->seedPackages();

            $this->seedLogi();
            $this->seedYildizMobilya();
            $this->seedAydinHukuk();
            $this->seedDenizLojistik();
            $this->seedEgeYazilim();
            $this->seedKaradenizDernek();
            $this->seedAnadoluGida();
            $this->seedKayaMatbaa();
            $this->seedGunesTekstil();
        } finally {
            Carbon::setTestNow($previousTestNow);
        }
    }

    private function seedCatalog(): void
    {
        $this->catalogService('hosting_1000', ServiceType::Hosting, 'Linux Hosting 1000 MB', '1.500', Currency::Try, [
            'provider' => 'Natro', 'disk_space' => '1000 MB', 'monthly_traffic' => '10 GB',
            'email_accounts' => '10', 'databases' => '5', 'sites' => '1',
        ]);
        $this->catalogService('hosting_starter', ServiceType::Hosting, 'Linux Hosting Başlangıç', '2.500', Currency::Try, [
            'provider' => 'Natro', 'disk_space' => '5 GB', 'monthly_traffic' => '50 GB',
            'email_accounts' => '20', 'databases' => '10', 'sites' => '1',
        ]);
        $this->catalogService('hosting_business', ServiceType::Hosting, 'Linux Hosting Kurumsal', '6.000', Currency::Try, [
            'provider' => 'Natro', 'disk_space' => '20 GB', 'monthly_traffic' => 'Sınırsız',
            'email_accounts' => '100', 'databases' => '25', 'sites' => '5',
        ]);
        $this->catalogService('domain_com_tr', ServiceType::Domain, '.com.tr Alan Adı', '450', Currency::Try, [
            'registrar' => 'Nic.tr', 'extension' => '.com.tr',
        ]);
        $this->catalogService('domain_com', ServiceType::Domain, '.com Alan Adı', '15', Currency::Usd, [
            'registrar' => 'Namecheap', 'extension' => '.com',
        ]);
        $this->catalogService('ssl', ServiceType::Ssl, 'SSL Sertifikası (DV)', '1.200', Currency::Try, [
            'provider' => 'Sectigo', 'certificate_type' => 'DV',
        ]);
        $this->catalogService('email', ServiceType::Email, 'Kurumsal E-posta', '3.000', Currency::Try, [
            'provider' => 'Yandex', 'quota' => 'Hesap başına 10 GB',
        ]);
        $this->catalogService('maintenance', ServiceType::Maintenance, 'Web Sitesi Bakımı', '12.000', Currency::Try, [
            'scope' => 'Aylık güncelleme, yedek kontrolü ve küçük içerik değişiklikleri',
        ]);
        $this->catalogService('theme_license', ServiceType::License, 'Tema Lisansı', '59', Currency::Usd, [
            'product_name' => 'Astra Pro', 'provider' => 'Brainstorm Force',
        ]);
        $this->catalogService('hosting_legacy', ServiceType::Hosting, 'Linux Hosting Eski (500 MB)', '900', Currency::Try, [
            'provider' => 'Natro', 'disk_space' => '500 MB', 'monthly_traffic' => '5 GB',
            'email_accounts' => '5', 'databases' => '2', 'sites' => '1',
        ])->archive();
    }

    private function seedPackages(): void
    {
        $this->package('corporate', 'Kurumsal Paket', '4.000', ['hosting_1000', 'domain_com_tr', 'maintenance']);
        $this->package('professional', 'Profesyonel Paket', '9.500', ['hosting_business', 'domain_com_tr', 'ssl', 'email']);
        $this->package('starter', 'Başlangıç Paketi', '2.750', ['hosting_starter', 'domain_com_tr']);
    }

    /**
     * The PRD scenario: 5.000 TL paid for the 4.000 TL first charge, then the renewal
     * charge created with the 1.000 TL credit applied to it, 3.000 TL still open.
     */
    private function seedLogi(): void
    {
        $customer = $this->customer('Logi', 'Logitech Bilgisayar Ürünleri Ltd. Şti.', 'Kozyatağı', '6080512345', 'Kozyatağı Mah. Değirmen Sok. No: 18, Kadıköy / İstanbul', [
            ['name' => 'Murat Kaya', 'title' => 'Bilgi İşlem Sorumlusu', 'phone' => '0532 412 18 90', 'email' => 'murat.kaya@example.com'],
            ['name' => 'Elif Şahin', 'title' => 'Muhasebe', 'phone' => '0216 555 12 34', 'email' => 'muhasebe.logi@example.com'],
        ]);

        $start = $this->endingIn(20);

        $service = $this->assign($customer, $this->packages['corporate'], $start, [
            ServiceType::Hosting->value => ['domain' => 'logi.com.tr', 'server' => 'srv14.natro.com', 'cpanel_username' => 'logicomt'],
            ServiceType::Domain->value => ['domain' => 'logi.com.tr'],
            ServiceType::Maintenance->value => ['domain' => 'logi.com.tr'],
        ]);

        $this->payment($customer, '5.000', Currency::Try, $start->addDays(3), 'Havale/EFT');

        $this->createRenewalCharge->handle($service);
    }

    private function seedYildizMobilya(): void
    {
        $customer = $this->customer('Yıldız Mobilya', 'Yıldız Mobilya San. ve Tic. A.Ş.', 'İnegöl', '9870123456', 'Organize Sanayi Bölgesi 3. Cad. No: 7, İnegöl / Bursa', [
            ['name' => 'Hasan Yıldız', 'title' => 'Genel Müdür', 'phone' => '0533 280 44 12', 'email' => 'hasan.yildiz@example.com'],
        ]);

        $start = $this->endingIn(15);
        $previousStart = $start->subMonthsNoOverflow(12);

        $package = $this->assign($customer, $this->packages['professional'], $previousStart, [
            ServiceType::Hosting->value => ['domain' => 'yildizmobilya.com.tr', 'server' => 'srv08.natro.com', 'cpanel_username' => 'yildizmo'],
            ServiceType::Domain->value => ['domain' => 'yildizmobilya.com.tr'],
            ServiceType::Ssl->value => ['domain' => 'yildizmobilya.com.tr'],
            ServiceType::Email->value => ['domain' => 'yildizmobilya.com.tr', 'account_count' => '12'],
        ]);

        $this->payment($customer, '9.500', Currency::Try, $previousStart->addDays(5), 'Havale/EFT');

        $this->at($start, fn () => $this->advanceCustomerServicePeriod->handle($package));
        $this->payment($customer, '9.500', Currency::Try, $start->addDays(2), 'Havale/EFT');

        $maintenanceStart = $this->today->subMonthsNoOverflow(4);
        $this->assign($customer, $this->catalog['maintenance'], $maintenanceStart, [
            ServiceType::Maintenance->value => ['domain' => 'yildizmobilya.com.tr'],
        ]);
        $this->payment($customer, '12.000', Currency::Try, $maintenanceStart->addDays(10), 'Kredi Kartı');

        $this->createRenewalCharge->handle($package);
    }

    private function seedAydinHukuk(): void
    {
        $customer = $this->customer('Aydın Hukuk', 'Aydın Hukuk Bürosu', 'Çankaya', '3450987612', 'Kızılay Mah. Atatürk Blv. No: 112/6, Çankaya / Ankara', [
            ['name' => 'Av. Zeynep Aydın', 'title' => 'Kurucu Ortak', 'phone' => '0312 419 22 30', 'email' => 'zeynep.aydin@example.com'],
        ]);

        $start = $this->today->subMonthsNoOverflow(2);

        $this->assign($customer, $this->packages['starter'], $start, [
            ServiceType::Hosting->value => ['domain' => 'aydinhukuk.com.tr', 'server' => 'srv11.natro.com', 'cpanel_username' => 'aydinhuk'],
            ServiceType::Domain->value => ['domain' => 'aydinhukuk.com.tr'],
        ]);

        $this->payment($customer, '1.500', Currency::Try, $start->addDays(7), 'Havale/EFT');
    }

    private function seedDenizLojistik(): void
    {
        $customer = $this->customer('Deniz Lojistik', 'Deniz Lojistik ve Taşımacılık Ltd. Şti.', 'Konak', '2890456123', 'Alsancak Mah. Liman Cad. No: 41, Konak / İzmir', [
            ['name' => 'Burak Demir', 'title' => 'Operasyon Müdürü', 'phone' => '0542 118 70 05', 'email' => 'burak.demir@example.com'],
        ]);

        $start = $this->today->subMonthsNoOverflow(3);

        $this->assign($customer, $this->catalog['hosting_business'], $start, [
            ServiceType::Hosting->value => ['domain' => 'denizlojistik.com.tr', 'server' => 'srv03.natro.com', 'cpanel_username' => 'denizloj'],
        ]);
        $this->assign($customer, $this->catalog['domain_com_tr'], $start, [
            ServiceType::Domain->value => ['domain' => 'denizlojistik.com.tr'],
        ]);
    }

    /**
     * USD records: a paid domain, a paid license with its key and a USD credit balance,
     * next to an overdue TRY charge the USD credit must not touch.
     */
    private function seedEgeYazilim(): void
    {
        $customer = $this->customer('Ege Yazılım', 'Ege Yazılım ve Danışmanlık Ltd. Şti.', 'Bornova', '3310789045', 'Ege Üniversitesi Teknopark, Bornova / İzmir', [
            ['name' => 'Can Öztürk', 'title' => 'Kurucu', 'phone' => '0535 610 90 21', 'email' => 'can.ozturk@example.com'],
        ]);

        $domainStart = $this->today->subMonthsNoOverflow(6);
        $this->assign($customer, $this->catalog['domain_com'], $domainStart, [
            ServiceType::Domain->value => ['domain' => 'egeyazilim.com'],
        ]);
        $this->payment($customer, '15', Currency::Usd, $domainStart, 'Kredi Kartı');

        $licenseStart = $this->today->subMonthsNoOverflow(1);
        $this->assign($customer, $this->catalog['theme_license'], $licenseStart, [
            ServiceType::License->value => ['quantity' => '1'],
        ], licenseKey: 'ASTRA-7F3K9-2QX8M-PL45T');
        $this->payment($customer, '80', Currency::Usd, $licenseStart->addDays(1), 'Havale/EFT');

        $this->assign($customer, $this->catalog['hosting_starter'], $this->today->subDays(5), [
            ServiceType::Hosting->value => ['domain' => 'egeyazilim.com', 'server' => 'srv11.natro.com', 'cpanel_username' => 'egeyazil'],
        ]);
    }

    private function seedKaradenizDernek(): void
    {
        $customer = $this->customer('Karadeniz Doğa Derneği', 'Karadeniz Doğa ve Çevre Koruma Derneği', 'Trabzon', '5120334478', 'Kemerkaya Mah. Uzun Sok. No: 9, Ortahisar / Trabzon', [
            ['name' => 'Ayşe Karadeniz', 'title' => 'Dernek Başkanı', 'phone' => '0462 321 45 67', 'email' => 'ayse.karadeniz@example.com'],
        ]);

        $this->assign($customer, $this->catalog['hosting_starter'], $this->endingIn(25), [
            ServiceType::Hosting->value => ['domain' => 'karadenizdoga.org', 'server' => 'srv08.natro.com', 'cpanel_username' => 'kdoga'],
        ], ['is_free' => true, 'notes' => 'Dernek desteği olarak ücretsiz.']);
    }

    /**
     * A service cancelled after its period started: its charge is cancelled by hand,
     * since cancelling the service keeps the charge of a period that has started.
     */
    private function seedAnadoluGida(): void
    {
        $customer = $this->customer('Anadolu Gıda', 'Anadolu Gıda Pazarlama A.Ş.', 'Selçuklu', '0780654321', 'Sanayi Mah. Ankara Yolu No: 220, Selçuklu / Konya', [
            ['name' => 'Mehmet Arslan', 'title' => 'Satın Alma', 'phone' => '0332 248 60 10', 'email' => 'mehmet.arslan@example.com'],
        ]);

        $this->assign($customer, $this->catalog['email'], $this->today->subMonthsNoOverflow(10), [
            ServiceType::Email->value => ['domain' => 'anadolugida.com.tr', 'account_count' => '8'],
        ]);

        $sslStart = $this->today->subMonthsNoOverflow(1);
        $ssl = $this->assign($customer, $this->catalog['ssl'], $sslStart, [
            ServiceType::Ssl->value => ['domain' => 'anadolugida.com.tr'],
        ]);

        $this->at($sslStart->addDays(10), function () use ($ssl): void {
            $this->cancelCustomerService->handle($ssl, cancelNote: 'Müşteri SSL sertifikasını kendisi aldı.');
            $this->cancelCharge->handle($this->periodCharge($ssl), __('customer_services.charge_cancel_reason_service_cancelled'));
        });

        $this->payment($customer, '3.500', Currency::Try, $this->today->subDays(10), 'Havale/EFT', 'Fazla gönderildi, bakiyede kalacak.');
    }

    private function seedKayaMatbaa(): void
    {
        $customer = $this->customer('Kaya Matbaa', null, null, null, 'Ostim OSB 1203. Cad. No: 15, Yenimahalle / Ankara', [
            ['name' => 'Selim Kaya', 'title' => null, 'phone' => '0505 390 11 76', 'email' => 'selim.kaya@example.com'],
        ]);

        $this->assign($customer, $this->catalog['domain_com_tr'], $this->today, [
            ServiceType::Domain->value => ['domain' => 'kayamatbaa.com.tr'],
        ]);
    }

    /**
     * A customer whose only service was cancelled before its renewal window, then archived.
     */
    private function seedGunesTekstil(): void
    {
        $customer = $this->customer('Güneş Tekstil', 'Güneş Tekstil Konfeksiyon Ltd. Şti.', 'Merkezefendi', '4150098765', 'Sümer Mah. 2480 Sok. No: 4, Merkezefendi / Denizli', [
            ['name' => 'Fatma Güneş', 'title' => 'Muhasebe', 'phone' => '0258 263 77 40', 'email' => 'fatma.gunes@example.com'],
        ]);

        $start = $this->today->subMonthsNoOverflow(27);
        $service = $this->assign($customer, $this->catalog['hosting_legacy'], $start, [
            ServiceType::Hosting->value => ['domain' => 'gunestekstil.com.tr', 'server' => 'srv02.natro.com', 'cpanel_username' => 'gunestks'],
        ]);
        $this->payment($customer, '900', Currency::Try, $start->addDays(4), 'Kredi Kartı');

        $this->at(
            $service->end_date->toImmutable()->subDays(45),
            fn () => $this->cancelCustomerService->handle($service, cancelNote: 'Firma faaliyetini durdurdu.'),
        );

        $this->archiveCustomer->handle($customer);
    }

    /**
     * @param  array<string, string>  $details
     */
    private function catalogService(string $key, ServiceType $type, string $name, string $price, Currency $currency, array $details): CatalogService
    {
        return $this->catalog[$key] = CatalogService::create([
            'type' => $type,
            'name' => $name,
            'price' => Money::parseAmount($price),
            'currency' => $currency,
            'details' => $details,
        ]);
    }

    /**
     * @param  list<string>  $catalogKeys
     */
    private function package(string $key, string $name, string $price, array $catalogKeys): void
    {
        $package = Package::create([
            'name' => $name,
            'price' => Money::parseAmount($price),
            'currency' => Currency::Try,
        ]);

        $package->catalogServices()->attach(array_map(fn (string $catalogKey): int => $this->catalog[$catalogKey]->id, $catalogKeys));

        $this->packages[$key] = $package;
    }

    /**
     * @param  list<array{name: string, title: ?string, phone: string, email: string}>  $contacts
     */
    private function customer(string $shortName, ?string $legalTitle, ?string $taxOffice, ?string $taxNumber, string $billingAddress, array $contacts): Customer
    {
        $customer = Customer::create([
            'short_name' => $shortName,
            'legal_title' => $legalTitle,
            'tax_office' => $taxOffice,
            'tax_number' => $taxNumber,
            'billing_address' => $billingAddress,
        ]);

        foreach ($contacts as $contact) {
            $this->createContact->handle($customer, $contact);
        }

        return $customer;
    }

    /**
     * Assign a catalog service or package on its start date, with the customer's details
     * of each sold catalog service; the first period charge is created unless it is free.
     *
     * @param  array<string, array<string, string>>  $detailsByType  keyed by ServiceType value
     * @param  array{is_free?: bool, notes?: ?string}  $attributes
     */
    private function assign(Customer $customer, CatalogService|Package $sold, CarbonImmutable $start, array $detailsByType, array $attributes = [], ?string $licenseKey = null): CustomerService
    {
        $catalogServices = $sold instanceof Package ? $sold->catalogServices : collect([$sold]);

        $items = [];

        foreach ($catalogServices as $catalogService) {
            $items[$catalogService->id] = [
                'details' => $detailsByType[$catalogService->type->value] ?? [],
                'license_key' => $licenseKey,
            ];
        }

        return $this->at($start, fn (): CustomerService => $this->assignCustomerService->handle($customer, $sold, [
            'start_date' => $start->toDateString(),
            'items' => $items,
            ...$attributes,
        ]));
    }

    /**
     * Record the payment on the day it was paid; it is allocated automatically and any
     * surplus stays as credit balance.
     */
    private function payment(Customer $customer, string $amount, Currency $currency, CarbonImmutable $paidOn, string $method, ?string $note = null): Payment
    {
        return $this->at($paidOn, fn (): Payment => $this->recordPayment->handle($customer, [
            'amount' => Money::parseAmount($amount),
            'currency' => $currency,
            'paid_on' => $paidOn->toDateString(),
            'payment_method_id' => PaymentMethod::query()->where('name', $method)->firstOrFail()->id,
            'note' => $note,
        ]));
    }

    /**
     * The charge of the customer service's current period.
     */
    private function periodCharge(CustomerService $customerService): Charge
    {
        return $customerService->charges()
            ->whereDate('period_start', $customerService->refresh()->start_date->toDateString())
            ->sole();
    }

    /**
     * Run the callback with the clock set to the given date, then set it back to now.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function at(CarbonImmutable $date, Closure $callback): mixed
    {
        Carbon::setTestNow($date);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($this->now);
        }
    }

    /**
     * The start date of a yearly service ending about the given number of days from today.
     */
    private function endingIn(int $days): CarbonImmutable
    {
        return $this->today->addDays($days)->subMonthsNoOverflow(12);
    }
}
