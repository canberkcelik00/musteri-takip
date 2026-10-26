<?php

use App\Enums\ServiceType;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Payments\Index as PaymentsIndex;
use App\Livewire\Topbar\Search;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('preloads the latin Inter subset in the document head', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('rel="preload" as="font" type="font/woff2" crossorigin', false);
});

it('keeps the preloaded font in the built manifest so the real page resolves it', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

    expect($manifest)->toHaveKey('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2');
});

it('does not bundle an unused web font', function () {
    expect(file_get_contents(base_path('vite.config.js')))->not->toContain('bunny(');
});

it('prefetches sidebar and tab bar pages on hover', function () {
    foreach ([
        'resources/views/components/ui/nav-item.blade.php',
        'resources/views/layouts/app.blade.php',
    ] as $view) {
        $source = file_get_contents(base_path($view));

        expect($source)->toContain('wire:navigate.hover')
            ->and($source)->not->toMatch('/wire:navigate(?!\.hover)/');
    }
});

it('paints the page gradient on a fixed layer without a blur', function () {
    $css = file_get_contents(base_path('resources/css/ui/base.css'));

    preg_match('/body::before\s*\{[^}]*\}/', $css, $layer);

    expect($layer)->not->toBeEmpty()
        ->and($layer[0])->toContain('position: fixed')
        ->and($layer[0])->not->toContain('backdrop-filter');
});

it('searches nothing below two characters and keeps the result box closed', function () {
    $this->actingAs(User::factory()->create());
    Customer::factory()->create(['short_name' => 'Logi']);

    DB::enableQueryLog();
    $one = Livewire::test(Search::class)->set('query', 'l');

    expect($one->instance()->results())->toHaveCount(0)
        ->and(DB::getQueryLog())->toBe([]);

    $one->assertDontSee('ui-search-results', false)
        ->set('query', 'lo')->assertSee('Logi')->assertSee('ui-search-results', false);
});

it('loads only the columns and relations the search result row shows', function () {
    $this->actingAs(User::factory()->create());
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'notes' => 'private note']);
    CustomerService::factory()->for($customer)->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))->create();

    $found = Livewire::test(Search::class)->set('query', 'logi')->instance()->results()->first();

    expect($found->getAttributes())->not->toHaveKey('notes')
        ->and($found->customerServices)->toHaveCount(1);
});

it('reads the customer filter options once and again only after a customer changes', function (string $page) {
    $this->actingAs(User::factory()->create());
    Customer::factory()->create(['short_name' => 'Birinci']);

    $component = Livewire::test($page)->assertSee('Birinci');

    Customer::factory()->create(['short_name' => 'Ikinci']);
    DB::enableQueryLog();
    $component->set('search', 'x');
    $customerQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'from `customers` order by `short_name`'));

    expect($customerQueries)->toHaveCount(0);

    $component->dispatch('customers-changed')->assertSee('Ikinci');
})->with([
    'açık borçlar' => [ChargesIndex::class],
    'ödemeler' => [PaymentsIndex::class],
]);
