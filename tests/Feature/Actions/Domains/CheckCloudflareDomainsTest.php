<?php

use App\Actions\CustomerServices\UpdateCustomerService;
use App\Actions\Domains\CheckCloudflareDomains;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Support\Dns\NameserverLookup;

/**
 * Replaces the DNS lookup with answers from `$answers` (host => name servers, null = lookup failed);
 * a host that is not listed has no NS record. The returned object records every host asked.
 *
 * @param  array<string, list<string>|null>  $answers
 * @return object{asked: list<string>}
 */
function fakeNameservers(array $answers): object
{
    $lookup = new class($answers) extends NameserverLookup
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param  array<string, list<string>|null>  $answers */
        public function __construct(private readonly array $answers) {}

        public function nameserversOf(string $host): ?array
        {
            $this->asked[] = $host;

            return array_key_exists($host, $this->answers) ? $this->answers[$host] : [];
        }
    };

    app()->instance(NameserverLookup::class, $lookup);

    return $lookup;
}

function domainItem(?string $domain, array $serviceAttributes = []): CustomerServiceItem
{
    $service = CustomerService::factory()
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->create($serviceAttributes);

    $item = $service->items()->firstOrFail();
    $item->update(['details' => ['domain' => $domain]]);

    return $item->refresh();
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

it('marks a domain with a Cloudflare name server', function () {
    fakeNameservers(['logi.com.tr' => ['ann.ns.cloudflare.com', 'bob.ns.cloudflare.com']]);
    $item = domainItem('logi.com.tr');

    $result = app(CheckCloudflareDomains::class)->handle();

    expect($item->refresh()->is_cloudflare)->toBeTrue()
        ->and($item->dns_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
        ->and([$result->checked, $result->failed, $result->remaining])->toBe([1, 0, 0]);
});

it('marks a domain with other name servers as not on Cloudflare', function () {
    fakeNameservers(['logi.com.tr' => ['ns1.hosting.example', 'ns2.hosting.example']]);
    $item = domainItem('logi.com.tr');

    app(CheckCloudflareDomains::class)->handle();

    expect($item->refresh()->is_cloudflare)->toBeFalse()->and($item->dns_checked_at)->not->toBeNull();
});

it('asks the parent domain when a sub domain has no name server of its own', function () {
    $lookup = fakeNameservers(['ornek.com' => ['ann.ns.cloudflare.com']]);
    $item = domainItem('Shop.Ornek.com');

    app(CheckCloudflareDomains::class)->handle();

    expect($lookup->asked)->toBe(['shop.ornek.com', 'ornek.com'])->and($item->refresh()->is_cloudflare)->toBeTrue();
});

it('marks a domain without any name server as not on Cloudflare', function () {
    fakeNameservers([]);
    $item = domainItem('yok.example');

    app(CheckCloudflareDomains::class)->handle();

    expect($item->refresh()->is_cloudflare)->toBeFalse();
});

it('keeps the earlier answer when the lookup fails but counts the attempt', function () {
    fakeNameservers(['logi.com.tr' => null]);
    $item = domainItem('logi.com.tr');
    $item->forceFill(['is_cloudflare' => true, 'dns_checked_at' => '2026-06-01 09:00:00'])->saveQuietly();

    $result = app(CheckCloudflareDomains::class)->handle();

    expect($item->refresh()->is_cloudflare)->toBeTrue()
        ->and($item->dns_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
        ->and([$result->checked, $result->failed])->toBe([0, 1]);
});

it('looks a domain up again only after 24 hours', function () {
    $lookup = fakeNameservers(['logi.com.tr' => ['ann.ns.cloudflare.com']]);
    domainItem('logi.com.tr');

    app(CheckCloudflareDomains::class)->handle();
    $this->travelTo('2026-06-16 09:59:00');
    app(CheckCloudflareDomains::class)->handle();

    expect($lookup->asked)->toHaveCount(1);

    $this->travelTo('2026-06-16 10:00:00');
    app(CheckCloudflareDomains::class)->handle();

    expect($lookup->asked)->toHaveCount(2);
});

it('asks a domain used by several items once and writes it to all of them', function () {
    $lookup = fakeNameservers(['logi.com.tr' => ['ann.ns.cloudflare.com']]);
    $first = domainItem('logi.com.tr');
    $second = domainItem('LOGI.com.tr');

    app(CheckCloudflareDomains::class)->handle();

    expect($lookup->asked)->toBe(['logi.com.tr'])
        ->and($first->refresh()->is_cloudflare)->toBeTrue()
        ->and($second->refresh()->is_cloudflare)->toBeTrue();
});

it('skips cancelled services, empty domains and values that are not host names', function () {
    $lookup = fakeNameservers(['logi.com.tr' => ['ann.ns.cloudflare.com']]);
    $items = [
        domainItem('logi.com.tr', ['status' => CustomerServiceStatus::Cancelled]),
        domainItem(null),
        domainItem('logi com/tr'),
        domainItem('localhost'),
    ];

    $result = app(CheckCloudflareDomains::class)->handle();

    expect($lookup->asked)->toBe([])
        ->and($result->checked)->toBe(0)
        ->and(collect($items)->map(fn (CustomerServiceItem $item) => $item->refresh()->dns_checked_at)->filter()->all())->toBe([]);
});

it('stops at the time budget and does the rest in the next run, never tried first', function () {
    $lookup = fakeNameservers([]);
    $old = domainItem('eski.example');
    $old->forceFill(['dns_checked_at' => '2026-06-10 09:00:00'])->saveQuietly();
    $fresh = domainItem('yeni.example');

    $first = app(CheckCloudflareDomains::class)->handle(0);

    expect($lookup->asked)->toBe(['yeni.example'])
        ->and($first->checked)->toBe(1)
        ->and($first->remaining)->toBe(1)
        ->and($fresh->refresh()->dns_checked_at)->not->toBeNull()
        ->and($old->refresh()->dns_checked_at->toDateTimeString())->toBe('2026-06-10 09:00:00');

    $second = app(CheckCloudflareDomains::class)->handle(0);

    expect($lookup->asked)->toBe(['yeni.example', 'eski.example'])->and($second->remaining)->toBe(0);
});

it('forgets the answer when the item gets another domain', function () {
    fakeNameservers(['logi.com.tr' => ['ann.ns.cloudflare.com']]);
    $item = domainItem('logi.com.tr');
    app(CheckCloudflareDomains::class)->handle();
    expect($item->refresh()->is_cloudflare)->toBeTrue();

    app(UpdateCustomerService::class)->handle($item->customerService, [
        'items' => [$item->id => ['details' => ['domain' => 'baska.com']]],
    ]);

    expect($item->refresh()->is_cloudflare)->toBeNull()->and($item->dns_checked_at)->toBeNull();
});

it('keeps the answer when the item is saved with the same domain', function () {
    fakeNameservers(['logi.com.tr' => ['ann.ns.cloudflare.com']]);
    $item = domainItem('logi.com.tr');
    app(CheckCloudflareDomains::class)->handle();

    app(UpdateCustomerService::class)->handle($item->customerService, [
        'items' => [$item->id => ['details' => ['domain' => ' Logi.com.tr ']]],
    ]);

    expect($item->refresh()->is_cloudflare)->toBeTrue();
});
