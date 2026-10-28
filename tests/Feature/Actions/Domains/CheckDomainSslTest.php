<?php

use App\Actions\Domains\CheckDomainSsl;
use App\Enums\ServiceType;
use App\Livewire\Windows\CheckDomain;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\User;
use App\Support\Dns\TlsCertificate;
use App\Support\Dns\TlsCertificateLookup;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Binds a certificate reader that answers the given expiry (null = could not be read) and remembers what it was asked.
 */
function fakeLiveCertificate(?string $expires): object
{
    $lookup = new class($expires) extends TlsCertificateLookup
    {
        /** @var list<string> */
        public array $asked = [];

        public function __construct(private readonly ?string $expires) {}

        public function certificateOf(string $host, int $timeoutSeconds = 5): ?TlsCertificate
        {
            $this->asked[] = $host;

            return $this->expires === null
                ? null
                : new TlsCertificate(CarbonImmutable::parse($this->expires, 'Europe/Istanbul'), 'Let\'s Encrypt', $host);
        }
    };

    app()->instance(TlsCertificateLookup::class, $lookup);

    return $lookup;
}

function sslWindowItem(string $domain, ?string $storedExpiry = null): CustomerServiceItem
{
    $service = CustomerService::factory()
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->for(Customer::factory()->create())
        ->create();

    $item = $service->items()->firstOrFail();
    $item->update(['details' => ['domain' => $domain]]);
    $item->forceFill(['ssl_expires_at' => $storedExpiry, 'ssl_checked_at' => $storedExpiry === null ? null : now()])->saveQuietly();

    return $item->refresh();
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

it('reads the certificate live and counts the days left', function () {
    $fake = fakeLiveCertificate('2026-07-01 12:00:00');

    $answer = app(CheckDomainSsl::class)->handle(' Logi.com.TR. ');

    expect($answer)->toBe([
        'expires_on' => '2026-07-01',
        'days_left' => 16,
        'issuer' => 'Let\'s Encrypt',
        'subject' => 'logi.com.tr',
        'stored' => false,
    ])->and($fake->asked)->toBe(['logi.com.tr']);
});

it('reports a negative day count once the certificate has ended', function () {
    fakeLiveCertificate('2026-06-10 12:00:00');

    expect(app(CheckDomainSsl::class)->handle('logi.com.tr')['days_left'])->toBe(-5);
});

it('refuses an invalid host without asking', function () {
    $fake = fakeLiveCertificate('2026-07-01 12:00:00');

    expect(app(CheckDomainSsl::class)->handle('not a domain'))->toBeNull()
        ->and($fake->asked)->toBe([]);
});

it('shows the stored expiry, flagged, when the live read fails', function () {
    fakeLiveCertificate(null);
    sslWindowItem('logi.com.tr', '2026-06-20 00:00:00');

    $answer = app(CheckDomainSsl::class)->handle('logi.com.tr');

    expect($answer['stored'])->toBeTrue()
        ->and($answer['expires_on'])->toBe('2026-06-20')
        ->and($answer['days_left'])->toBe(5)
        ->and($answer['issuer'])->toBeNull();
});

it('is unreadable when the live read fails and nothing is stored', function () {
    fakeLiveCertificate(null);
    sslWindowItem('logi.com.tr');

    expect(app(CheckDomainSsl::class)->handle('logi.com.tr'))->toBeNull();
});

it('reads the ssl tab once it is opened and again on refresh', function () {
    $this->actingAs(User::factory()->developer()->create());
    $fake = fakeLiveCertificate('2026-06-20 12:00:00');

    $window = Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->assertSet('sslLoaded', false);

    expect($fake->asked)->toBe([]);

    $window->call('loadSsl')
        ->assertSet('sslLoaded', true)
        ->assertSee('20.06.2026')
        ->assertSee('Let\'s Encrypt')
        ->assertSee(__('domain_check.ssl_soon', ['days' => 5]));

    $window->call('loadSsl')->call('refresh');

    expect($fake->asked)->toHaveCount(2);
});

it('says the certificate has expired and when the read is impossible', function () {
    $this->actingAs(User::factory()->admin()->create());
    fakeLiveCertificate('2026-06-10 12:00:00');

    Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->call('loadSsl')
        ->assertSee(__('domain_check.ssl_expired', ['days' => 5]));

    fakeLiveCertificate(null);

    Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->call('loadSsl')
        ->assertSee(__('domain_check.unreadable'));
});
