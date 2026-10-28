<?php

use App\Enums\DnsRecordType;
use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Livewire\Pages\Customers\Show;
use App\Livewire\Topbar\Search;
use App\Livewire\WindowHost;
use App\Livewire\Windows\CheckDomain;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use App\Support\Dns\DnsLookup;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Binds a DNS lookup that answers from a table (type value => rows; null = could not be read, missing = no record)
 * and remembers what it was asked.
 *
 * @param  array<string, list<string>|null>  $answers
 */
function fakeDnsAnswers(array $answers): DnsLookup
{
    $fake = new class($answers) extends DnsLookup
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param array<string, list<string>|null> $answers */
        public function __construct(private readonly array $answers) {}

        public function records(string $host, DnsRecordType $type): ?array
        {
            $this->asked[] = "{$host} {$type->value}";

            return array_key_exists($type->value, $this->answers) ? $this->answers[$type->value] : [];
        }
    };

    app()->instance(DnsLookup::class, $fake);

    return $fake;
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('reads every record type of the domain once the window is open and shows them', function () {
    $fake = fakeDnsAnswers([
        'a' => ['1.2.3.4'],
        'mx' => ['10 mail.logi.com.tr'],
        'txt' => ['v=spf1 -all'],
        'ns' => null,
    ]);

    Livewire::test(CheckDomain::class, ['domain' => ' Logi.com.TR. '])
        ->assertSee(__('domain_check.loading'))
        ->assertSet('loaded', false)
        ->call('load')
        ->assertSet('loaded', true)
        ->assertSee('1.2.3.4')
        ->assertSee('10 mail.logi.com.tr')
        ->assertSee('v=spf1 -all')
        ->assertSee(__('domain_check.unreadable'))
        ->assertSee(__('domain_check.no_record'));

    expect($fake->asked)->toHaveCount(6)->and($fake->asked[0])->toBe('logi.com.tr a');
});

it('does not ask DNS again on a re-render', function () {
    $fake = fakeDnsAnswers(['a' => ['1.2.3.4']]);

    Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->call('load')
        ->set('typed', 'logi.com.tr');

    expect($fake->asked)->toHaveCount(6);
});

it('refuses an invalid domain without asking DNS', function (string $input) {
    $fake = fakeDnsAnswers([]);

    Livewire::test(CheckDomain::class, ['domain' => $input])
        ->call('load')
        ->assertSet('host', null)
        ->assertSet('loaded', false)
        ->assertSee(__('domain_check.invalid', ['domain' => $input]));

    expect($fake->asked)->toBe([]);
})->with(['not a host', 'logi', '192.168.1.1']);

it('shows an unreadable lookup as "Okunamadı", not as an error', function () {
    fakeDnsAnswers(['a' => null, 'aaaa' => null, 'mx' => null, 'ns' => null, 'txt' => null, 'cname' => null]);

    $html = Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])->call('load')->html();

    expect(substr_count($html, __('domain_check.unreadable')))->toBe(6)->and($html)->not->toContain('ui-error-box');
});

it('lets a developer open it through the window host', function () {
    Livewire::actingAs(User::factory()->developer()->create())
        ->test(WindowHost::class)
        ->dispatch('open-window', name: 'check-domain', params: ['domain' => 'logi.com.tr'])
        ->assertSet('name', 'check-domain');
});

it('forbids a user without the services permission', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);

    Livewire::actingAs(User::factory()->developer()->create())
        ->test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->assertForbidden();
});

it('puts "Alan adını kontrol et" in the search when the term is a domain', function () {
    $html = Livewire::test(Search::class)
        ->set('query', 'Logi.com.tr')
        ->assertSee(__('domain_check.search_row', ['domain' => 'logi.com.tr']))
        ->assertDontSee(__('ui.search.empty'))
        ->html();

    expect($html)->toContain("name: 'check-domain', params: { domain: 'logi.com.tr' }");
});

it('offers no domain row for a word, a customer name or a half-typed address', function (string $term) {
    Livewire::test(Search::class)->set('query', $term)->assertDontSee(__('domain_check.search_row', ['domain' => $term]));
})->with(['logi', 'logi hosting', 'logi.', 'https://logi.com.tr']);

it('offers the search row to a developer', function () {
    Livewire::actingAs(User::factory()->developer()->create())
        ->test(Search::class)
        ->set('query', 'logi.com')
        ->assertSee(__('domain_check.search_row', ['domain' => 'logi.com']));
});

it('hides the search row from a user without the services permission', function () {
    RoleModel::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);

    Livewire::actingAs(User::factory()->developer()->create())
        ->test(Search::class)
        ->set('query', 'logi.com')
        ->assertDontSee(__('domain_check.search_row', ['domain' => 'logi.com']));
});

it('adds "Kontrol et" to a domain row only when asked to', function () {
    $with = Blade::render('<x-ui.domains :domains="[\'Logi.com.tr\']" :check="true" />');
    $without = Blade::render('<x-ui.domains :domains="[\'logi.com.tr\']" />');

    expect($with)->toContain(__('domain_check.check'))->toContain("name: 'check-domain', params: { domain: 'logi.com.tr' }")
        ->and($without)->not->toContain(__('domain_check.check'));
});

it('shows "Kontrol et" in the Domains card of a customer', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->create();
    $service->items()->first()->update(['details' => ['domain' => 'logi.com.tr']]);

    Livewire::test(Show::class, ['customer' => $customer])->assertSee(__('domain_check.check'));
});
