<?php

namespace Database\Seeders;

use App\Actions\Contacts\CreateContact;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
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
use Illuminate\Database\Seeder;

/**
 * Realistic Turkish sample data for local use. All dates are relative to today, so
 * paid, partially paid, overdue and upcoming charges stay so whenever it is run.
 *
 * The payment and assignment Actions do not exist yet (Phase 3), so records are
 * built directly and kept consistent by hand: a paid charge is fully allocated,
 * allocations stay within the payment's customer and currency, and a surplus is
 * left as credit only when no open charge of that currency could take it.
 */
class SampleDataSeeder extends Seeder
{
    private CarbonImmutable $today;

    /** @var array<string, CatalogService> */
    private array $catalog = [];

    /** @var array<string, Package> */
    private array $packages = [];

    public function __construct(private CreateContact $createContact) {}

    public function run(): void
    {
        $this->today = CarbonImmutable::today(config('app.timezone'));

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
        $this->catalogService('maintenance', ServiceType::Maintenance, 'WordPress Bakım', '12.000', Currency::Try, [
            'scope' => 'Aylık güncelleme, yedek kontrolü ve küçük içerik değişiklikleri',
        ]);
        $this->catalogService('theme_license', ServiceType::License, 'WordPress Tema Lisansı', '59', Currency::Usd, [
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
     * The PRD scenario: renewal charge created, the 1.000 TL surplus of the previous
     * payment applied to it, 3.000 TL still open.
     */
    private function seedLogi(): void
    {
        $customer = $this->customer('Logi', 'Logitech Bilgisayar Ürünleri Ltd. Şti.', 'Kozyatağı', '6080512345', 'Kozyatağı Mah. Değirmen Sok. No: 18, Kadıköy / İstanbul', [
            ['name' => 'Murat Kaya', 'title' => 'Bilgi İşlem Sorumlusu', 'phone' => '0532 412 18 90', 'email' => 'murat.kaya@example.com'],
            ['name' => 'Elif Şahin', 'title' => 'Muhasebe', 'phone' => '0216 555 12 34', 'email' => 'muhasebe.logi@example.com'],
        ]);

        $service = $this->assign($customer, $this->packages['corporate'], $this->endingIn(20), [
            ServiceType::Hosting->value => ['domain' => 'logi.com.tr', 'server' => 'srv14.natro.com', 'cpanel_username' => 'logicomt'],
            ServiceType::Domain->value => ['domain' => 'logi.com.tr'],
            ServiceType::Maintenance->value => ['domain' => 'logi.com.tr'],
        ]);

        $currentCharge = $this->periodCharge($service, $service->start_date->toImmutable(), ChargeStatus::Paid);
        $renewalCharge = $this->periodCharge($service, $service->end_date->toImmutable());

        $this->payment($customer, '5.000', Currency::Try, $service->start_date->toImmutable()->addDays(3), 'Havale/EFT', [
            [$currentCharge, '4.000'],
            [$renewalCharge, '1.000'],
        ]);
    }

    private function seedYildizMobilya(): void
    {
        $customer = $this->customer('Yıldız Mobilya', 'Yıldız Mobilya San. ve Tic. A.Ş.', 'İnegöl', '9870123456', 'Organize Sanayi Bölgesi 3. Cad. No: 7, İnegöl / Bursa', [
            ['name' => 'Hasan Yıldız', 'title' => 'Genel Müdür', 'phone' => '0533 280 44 12', 'email' => 'hasan.yildiz@example.com'],
        ]);

        $package = $this->assign($customer, $this->packages['professional'], $this->endingIn(15), [
            ServiceType::Hosting->value => ['domain' => 'yildizmobilya.com.tr', 'server' => 'srv08.natro.com', 'cpanel_username' => 'yildizmo'],
            ServiceType::Domain->value => ['domain' => 'yildizmobilya.com.tr'],
            ServiceType::Ssl->value => ['domain' => 'yildizmobilya.com.tr'],
            ServiceType::Email->value => ['domain' => 'yildizmobilya.com.tr', 'account_count' => '12'],
        ]);

        $start = $package->start_date->toImmutable();
        $previousStart = $start->subMonthsNoOverflow($package->period_months);

        $previousCharge = $this->periodCharge($package, $previousStart, ChargeStatus::Paid);
        $currentCharge = $this->periodCharge($package, $start, ChargeStatus::Paid);
        $this->periodCharge($package, $package->end_date->toImmutable());

        $this->payment($customer, '9.500', Currency::Try, $previousStart->addDays(5), 'Havale/EFT', [[$previousCharge, '9.500']]);
        $this->payment($customer, '9.500', Currency::Try, $start->addDays(2), 'Havale/EFT', [[$currentCharge, '9.500']]);

        $maintenance = $this->assign($customer, $this->catalog['maintenance'], $this->today->subMonthsNoOverflow(4), [
            ServiceType::Maintenance->value => ['domain' => 'yildizmobilya.com.tr'],
        ]);
        $maintenanceCharge = $this->periodCharge($maintenance, $maintenance->start_date->toImmutable(), ChargeStatus::Paid);
        $this->payment($customer, '12.000', Currency::Try, $maintenance->start_date->toImmutable()->addDays(10), 'Kredi Kartı', [[$maintenanceCharge, '12.000']]);
    }

    private function seedAydinHukuk(): void
    {
        $customer = $this->customer('Aydın Hukuk', 'Aydın Hukuk Bürosu', 'Çankaya', '3450987612', 'Kızılay Mah. Atatürk Blv. No: 112/6, Çankaya / Ankara', [
            ['name' => 'Av. Zeynep Aydın', 'title' => 'Kurucu Ortak', 'phone' => '0312 419 22 30', 'email' => 'zeynep.aydin@example.com'],
        ]);

        $service = $this->assign($customer, $this->packages['starter'], $this->today->subMonthsNoOverflow(2), [
            ServiceType::Hosting->value => ['domain' => 'aydinhukuk.com.tr', 'server' => 'srv11.natro.com', 'cpanel_username' => 'aydinhuk'],
            ServiceType::Domain->value => ['domain' => 'aydinhukuk.com.tr'],
        ]);
        $charge = $this->periodCharge($service, $service->start_date->toImmutable());

        $this->payment($customer, '1.500', Currency::Try, $service->start_date->toImmutable()->addDays(7), 'Nakit', [[$charge, '1.500']]);
    }

    private function seedDenizLojistik(): void
    {
        $customer = $this->customer('Deniz Lojistik', 'Deniz Lojistik ve Taşımacılık Ltd. Şti.', 'Konak', '2890456123', 'Alsancak Mah. Liman Cad. No: 41, Konak / İzmir', [
            ['name' => 'Burak Demir', 'title' => 'Operasyon Müdürü', 'phone' => '0542 118 70 05', 'email' => 'burak.demir@example.com'],
        ]);

        $start = $this->today->subMonthsNoOverflow(3);

        $hosting = $this->assign($customer, $this->catalog['hosting_business'], $start, [
            ServiceType::Hosting->value => ['domain' => 'denizlojistik.com.tr', 'server' => 'srv03.natro.com', 'cpanel_username' => 'denizloj'],
        ]);
        $domain = $this->assign($customer, $this->catalog['domain_com_tr'], $start, [
            ServiceType::Domain->value => ['domain' => 'denizlojistik.com.tr'],
        ]);

        $this->periodCharge($hosting, $start);
        $this->periodCharge($domain, $start);

        $customer->charges()->create([
            'description' => 'E-posta taşıma hizmeti',
            'amount' => Money::parseAmount('1.500'),
            'currency' => Currency::Try,
            'due_date' => $this->today->subDays(10)->toDateString(),
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

        $domain = $this->assign($customer, $this->catalog['domain_com'], $this->today->subMonthsNoOverflow(6), [
            ServiceType::Domain->value => ['domain' => 'egeyazilim.com'],
        ]);
        $domainCharge = $this->periodCharge($domain, $domain->start_date->toImmutable(), ChargeStatus::Paid);
        $this->payment($customer, '15', Currency::Usd, $domain->start_date->toImmutable(), 'Kredi Kartı', [[$domainCharge, '15']]);

        $license = $this->assign($customer, $this->catalog['theme_license'], $this->today->subMonthsNoOverflow(1), [
            ServiceType::License->value => ['quantity' => '1'],
        ]);
        $license->items()->firstOrFail()->update(['license_key' => 'ASTRA-7F3K9-2QX8M-PL45T']);
        $licenseCharge = $this->periodCharge($license, $license->start_date->toImmutable(), ChargeStatus::Paid);
        $this->payment($customer, '80', Currency::Usd, $license->start_date->toImmutable()->addDays(1), 'Havale/EFT', [[$licenseCharge, '59']]);

        $hosting = $this->assign($customer, $this->catalog['hosting_starter'], $this->today->subDays(5), [
            ServiceType::Hosting->value => ['domain' => 'egeyazilim.com', 'server' => 'srv11.natro.com', 'cpanel_username' => 'egeyazil'],
        ]);
        $this->periodCharge($hosting, $hosting->start_date->toImmutable());
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

    private function seedAnadoluGida(): void
    {
        $customer = $this->customer('Anadolu Gıda', 'Anadolu Gıda Pazarlama A.Ş.', 'Selçuklu', '0780654321', 'Sanayi Mah. Ankara Yolu No: 220, Selçuklu / Konya', [
            ['name' => 'Mehmet Arslan', 'title' => 'Satın Alma', 'phone' => '0332 248 60 10', 'email' => 'mehmet.arslan@example.com'],
        ]);

        $email = $this->assign($customer, $this->catalog['email'], $this->today->subMonthsNoOverflow(10), [
            ServiceType::Email->value => ['domain' => 'anadolugida.com.tr', 'account_count' => '8'],
        ]);
        $emailCharge = $this->periodCharge($email, $email->start_date->toImmutable(), ChargeStatus::Paid);

        $sslStart = $this->today->subMonthsNoOverflow(1);
        $sslCancelledOn = $sslStart->addDays(10);
        $ssl = $this->assign($customer, $this->catalog['ssl'], $sslStart, [
            ServiceType::Ssl->value => ['domain' => 'anadolugida.com.tr'],
        ], [
            'status' => CustomerServiceStatus::Cancelled,
            'cancelled_on' => $sslCancelledOn->toDateString(),
            'cancel_note' => 'Müşteri SSL sertifikasını kendisi aldı.',
        ]);
        $sslCharge = $this->periodCharge($ssl, $sslStart);
        $sslCharge->update([
            'status' => ChargeStatus::Cancelled,
            'cancelled_at' => $sslCancelledOn,
            'cancel_reason' => 'Hizmet iptal edildi.',
        ]);

        $this->payment($customer, '3.500', Currency::Try, $this->today->subDays(10), 'Havale/EFT', [[$emailCharge, '3.000']], 'Fazla gönderildi, bakiyede kalacak.');
    }

    private function seedKayaMatbaa(): void
    {
        $customer = $this->customer('Kaya Matbaa', null, null, null, 'Ostim OSB 1203. Cad. No: 15, Yenimahalle / Ankara', [
            ['name' => 'Selim Kaya', 'title' => null, 'phone' => '0505 390 11 76', 'email' => 'selim.kaya@example.com'],
        ]);

        $service = $this->assign($customer, $this->catalog['domain_com_tr'], $this->today, [
            ServiceType::Domain->value => ['domain' => 'kayamatbaa.com.tr'],
        ]);
        $this->periodCharge($service, $this->today);
    }

    private function seedGunesTekstil(): void
    {
        $customer = $this->customer('Güneş Tekstil', 'Güneş Tekstil Konfeksiyon Ltd. Şti.', 'Merkezefendi', '4150098765', 'Sümer Mah. 2480 Sok. No: 4, Merkezefendi / Denizli', [
            ['name' => 'Fatma Güneş', 'title' => 'Muhasebe', 'phone' => '0258 263 77 40', 'email' => 'fatma.gunes@example.com'],
        ]);

        $start = $this->today->subMonthsNoOverflow(27);
        $service = $this->assign($customer, $this->catalog['hosting_legacy'], $start, [
            ServiceType::Hosting->value => ['domain' => 'gunestekstil.com.tr', 'server' => 'srv02.natro.com', 'cpanel_username' => 'gunestks'],
        ]);
        $charge = $this->periodCharge($service, $start, ChargeStatus::Paid);
        $this->payment($customer, '900', Currency::Try, $start->addDays(4), 'Nakit', [[$charge, '900']]);

        $service->update([
            'status' => CustomerServiceStatus::Cancelled,
            'cancelled_on' => $service->end_date->toDateString(),
            'cancel_note' => 'Firma faaliyetini durdurdu.',
        ]);
        $customer->archive();
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
     * Sell a catalog service or package as on assignment: name, price and currency are
     * copied, and there is one item per catalog service with its customer details.
     *
     * @param  array<string, array<string, string>>  $detailsByType  keyed by ServiceType value
     * @param  array<string, mixed>  $attributes
     */
    private function assign(Customer $customer, CatalogService|Package $sold, CarbonImmutable $start, array $detailsByType, array $attributes = []): CustomerService
    {
        $service = $customer->customerServices()->create([
            'catalog_service_id' => $sold instanceof CatalogService ? $sold->id : null,
            'package_id' => $sold instanceof Package ? $sold->id : null,
            'name' => $sold->name,
            'price' => $sold->price,
            'currency' => $sold->currency,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addMonthsNoOverflow(12)->toDateString(),
            ...$attributes,
        ]);

        $catalogServices = $sold instanceof Package ? $sold->catalogServices : collect([$sold]);

        foreach ($catalogServices as $catalogService) {
            $service->items()->create([
                'catalog_service_id' => $catalogService->id,
                'type' => $catalogService->type,
                'name' => $catalogService->name,
                'details' => $detailsByType[$catalogService->type->value] ?? [],
            ]);
        }

        return $service;
    }

    /**
     * The charge of the service's period starting on the given date, due on that date.
     */
    private function periodCharge(CustomerService $service, CarbonImmutable $periodStart, ChargeStatus $status = ChargeStatus::Open): Charge
    {
        $periodEnd = $periodStart->addMonthsNoOverflow($service->period_months);

        /** @var Customer $customer */
        $customer = $service->customer;

        return $customer->charges()->create([
            'customer_service_id' => $service->id,
            'description' => $service->name.' '.$periodStart->format('Y').'–'.$periodEnd->format('Y'),
            'amount' => $service->price,
            'currency' => $service->currency,
            'due_date' => $periodStart->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'status' => $status,
        ]);
    }

    /**
     * @param  list<array{0: Charge, 1: string}>  $allocations  charge and allocated amount
     */
    private function payment(Customer $customer, string $amount, Currency $currency, CarbonImmutable $paidOn, string $method, array $allocations, ?string $note = null): Payment
    {
        $payment = $customer->payments()->create([
            'amount' => Money::parseAmount($amount),
            'currency' => $currency,
            'paid_on' => $paidOn->toDateString(),
            'payment_method_id' => PaymentMethod::query()->where('name', $method)->firstOrFail()->id,
            'note' => $note,
        ]);

        foreach ($allocations as [$charge, $allocated]) {
            $payment->allocations()->create([
                'charge_id' => $charge->id,
                'amount' => Money::parseAmount($allocated),
            ]);
        }

        return $payment;
    }

    /**
     * The start date of a yearly service ending about the given number of days from today.
     */
    private function endingIn(int $days): CarbonImmutable
    {
        return $this->today->addDays($days)->subMonthsNoOverflow(12);
    }
}
