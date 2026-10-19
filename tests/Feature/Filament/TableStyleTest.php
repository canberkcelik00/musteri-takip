<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Tables, status badges and filter chips (G-4B.4, docs/UI.md §5–6). The visual match with the
 * reference screens (docs/design/referans) is checked by eye; these tests pin what can be pinned:
 * colors only from tokens, one table look for every list, the four fixed badge colors, the filter
 * chips' remove button and the empty list's action button staying usable.
 */

/**
 * Selectors of the table rules in the theme CSS (each one sets at least one color).
 *
 * @return array<int, string>
 */
function tableRuleSelectors(): array
{
    return [
        '.fi-ta-ctn',
        '.fi-ta-ctn .fi-ta-search-field .fi-input-wrp',
        '.fi-ta-ctn .fi-ta-search-field .fi-input::placeholder',
        '.fi-ta-ctn .fi-ta-header-heading',
        '.fi-ta-ctn .fi-ta-header-description',
        '.fi-ta-ctn .fi-ta-filter-indicators',
        '.fi-ta-ctn .fi-ta-filter-indicators .fi-badge',
        '.fi-ta-ctn .fi-ta-filter-indicators .fi-badge-delete-btn',
        '.fi-ta-ctn .fi-ta-filter-indicators .fi-badge-delete-btn:hover',
        '.fi-ta-ctn .fi-pagination',
        '.fi-ta-ctn .fi-ta-table',
        '.fi-ta-ctn .fi-ta-table > :not(:last-child)',
        '.fi-ta-ctn .fi-ta-table > tbody > tr',
        '.fi-ta-ctn .fi-ta-header-cell',
        '.fi-ta-ctn .fi-ta-header-cell-sort-btn',
        '.fi-ta-ctn .fi-ta-row:hover',
        '.fi-ta-ctn .fi-ta-text-item',
        '.fi-ta-ctn .fi-ta-text-description',
        '.fi-ta-ctn .fi-money .fi-ta-text-item.fi-color-danger',
        '.fi-ta-ctn .fi-ta-table > tfoot',
        '.fi-ta-ctn .fi-ta-total-label',
        '.fi-ta-ctn .fi-ta-total-amounts',
        '.fi-ta-ctn .fi-ta-empty-state',
        '.fi-ta-ctn .fi-ta-empty-state-icon-bg',
        '.fi-ta-ctn .fi-ta-empty-state-icon-bg .fi-icon',
        '.fi-ta-ctn .fi-ta-empty-state-heading',
        '.fi-ta-ctn .fi-ta-empty-state-description',
        '.fi-today-tasks-heading',
        '.fi-today-tasks-count',
        '.fi-today-tasks-empty',
        '.fi-today-tasks .fi-ta-cell',
        '.fi-today-customer',
        '.fi-today-customer-title',
    ];
}

/**
 * The lists of the panel, each with its resource.
 *
 * @return array<string, string>
 */
function tableListUrls(): array
{
    return [
        'Müşteriler' => CustomerResource::getUrl('index'),
        'Açık borçlar' => ChargeResource::getUrl('index'),
        'Ödemeler' => PaymentResource::getUrl('index'),
        'Hizmetler' => CatalogServiceResource::getUrl('index'),
        'Paketler' => PackageResource::getUrl('index'),
        'Ödeme yöntemleri' => PaymentMethodResource::getUrl('index'),
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('has a rule for every table selector', function (string $selector) {
    expect(designRuleBodies($selector))->not->toBeEmpty("No rule for {$selector} in the theme CSS.");
})->with(tableRuleSelectors());

it('takes table colors only from tokens', function (string $selector) {
    $declarations = collect(designRuleBodies($selector))
        ->flatMap(designColorDeclarations(...))
        ->all();

    expect($declarations)->not->toBeEmpty("{$selector} sets no color.");

    foreach ($declarations as [$property, $value]) {
        expect($value)->toMatch(DESIGN_COLOR_VALUE, "{$selector} {$property}: {$value}");
    }
})->with(tableRuleSelectors());

it('styles every list from the same rules, none from a column of one list', function () {
    // Column-specific classes (`fi-ta-cell-open-debt` …) would make one list look different.
    expect(designThemeCss())->not->toMatch('/\.fi-ta-(cell|header-cell)-(?!sort-btn)[a-z]/');

    foreach (tableListUrls() as $list => $url) {
        $this->get($url)->assertOk()->assertSee('fi-ta-ctn', false);
    }
});

it('gives money columns the fi-money class and semibold amounts', function () {
    expect(MoneyColumn::make('amount')->getExtraCellAttributeBag()->get('class'))->toContain('fi-money')
        ->and(implode("\n", designRuleBodies('.fi-ta-ctn .fi-money .fi-ta-text-item')))->toContain('font-weight: 600');
});

it('draws each status badge in its own fixed token colors', function (string $selector, string $color) {
    $body = implode("\n", designRuleBodies($selector));

    expect($body)->toContain("--mt-badge-bg: var(--mt-badge-{$color}-bg)")
        ->and($body)->toContain("--mt-badge-fg: var(--mt-badge-{$color}-text)")
        ->and($body)->toContain("--mt-badge-dot: var(--mt-badge-{$color}-dot)");
})->with([
    'overdue is red' => ['.fi-badge.fi-color.fi-color-danger', 'red'],
    'due soon is amber' => ['.fi-badge.fi-color.fi-color-warning', 'amber'],
    'paid is green' => ['.fi-badge.fi-color.fi-color-success', 'green'],
    'cancelled and archived are gray (Filament draws gray without a color class)' => ['.fi-badge:not(.fi-color)', 'gray'],
]);

it('gives the status badges a 6 px dot, a 4 px corner and no ring, and leaves the filter chips out', function () {
    $selector = '.fi-badge.fi-color:is(.fi-color-danger, .fi-color-warning, .fi-color-success):not(.fi-ta-filter-indicators *)';
    $body = implode("\n", designRuleBodies($selector));

    expect($body)->toContain('border-radius: var(--mt-radius-badge)')
        ->and($body)->toContain('box-shadow: none')
        ->and($body)->toContain('var(--mt-badge-font-size)')
        ->and($body)->toContain('var(--mt-badge-font-weight)')
        ->and($body)->toContain('&::before')
        ->and($body)->toContain('width: var(--mt-badge-dot-size)')
        ->and($body)->toContain('background-color: var(--mt-badge-dot)');

    // The chips are not status badges: no dot rule reaches them.
    expect(designThemeCss())->toContain(':not(.fi-ta-filter-indicators *)');
    expect(implode("\n", designRuleBodies('.fi-ta-ctn .fi-ta-filter-indicators .fi-badge')))->not->toContain('::before');
});

it('never shows a badge without an explicit color, so the colorless-badge gray stays the cancelled/archived gray', function () {
    $violations = [];

    foreach (File::allFiles(app_path('Filament')) as $file) {
        $chunks = preg_split('/(?=(?:TextColumn|MoneyColumn|IconColumn)::make\()/', $file->getContents()) ?: [];

        foreach ($chunks as $chunk) {
            if (str_contains($chunk, '->badge(') && ! str_contains($chunk, '->color(')) {
                $violations[] = $file->getFilename().': ->badge() without ->color()';
            }
        }
    }

    foreach (File::allFiles(resource_path('views')) as $file) {
        preg_match_all('/<x-filament::badge\b[^>]*>/', $file->getContents(), $matches);

        foreach ($matches[0] as $tag) {
            if (! preg_match('/:?color=/', $tag)) {
                $violations[] = $file->getFilename().': <x-filament::badge> without color';
            }
        }
    }

    expect($violations)->toBe([]);
});

it('uses gray only for cancelled and archived', function () {
    $violations = [];

    foreach (File::allFiles(app_path('Filament')) as $file) {
        $contents = $file->getContents();

        // Enum-driven colors: the arm that returns gray must be the cancelled one.
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (str_contains($line, "=> 'gray'") && ! str_contains($line, 'Cancelled')) {
                $violations[] = $file->getFilename().': '.trim($line);
            }
        }

        // Badge columns with a fixed gray: the column's state must be the "Arşivlendi" status.
        preg_match_all('/->badge\(\)\s*->color\(\'gray\'\)(.{0,200})/s', $contents, $matches);

        foreach ($matches[1] as $rest) {
            if (! str_contains($rest, 'status_archived')) {
                $violations[] = $file->getFilename().': gray badge that is not "archived"';
            }
        }
    }

    expect($violations)->toBe([]);
});

it('renders the filter chip with a remove button that is a real button, and removing it widens the list', function () {
    $open = Charge::factory()->create();
    $paid = Charge::factory()->paid()->create();

    $page = Livewire::test(ListCharges::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$paid]);

    expect($page->html())->toContain('fi-ta-filter-indicators')
        ->and($page->html())->toMatch('/<button[^>]*class="[^"]*fi-badge-delete-btn/');

    $page->removeTableFilter('status')
        ->assertCanSeeTableRecords([$open, $paid]);
});

it('keeps the chips\' remove button visible, clickable and focusable in the CSS', function () {
    $css = (string) preg_replace('#/\*.*?\*/#s', '', designThemeCss());
    preg_match_all('/^[^{}@]*fi-badge-delete-btn[^{}]*\{([^{}]*)\}/m', $css, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $body) {
        expect($body)->not->toMatch('/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0\b|pointer-events\s*:\s*none/');
    }

    $focus = implode("\n", designRuleBodies('.fi-ta-ctn .fi-ta-filter-indicators .fi-badge-delete-btn:focus-visible'));

    expect($focus)->toContain('var(--mt-focus-width)')
        ->and($focus)->toContain('var(--mt-focus-color)');
});

it('keeps the empty list\'s action button: no rule hides it, and every list renders it', function () {
    $css = (string) preg_replace('#/\*.*?\*/#s', '', designThemeCss());
    preg_match_all('/^[^{}@]*fi-ta-empty-state[^{}]*\{([^{}]*)\}/m', $css, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $body) {
        expect($body)->not->toMatch('/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0\b/');
    }

    // The theme leaves the actions area of the empty state alone.
    expect($css)->not->toMatch('/fi-ta-empty-state[^{]*fi-ta-actions[^{]*\{/');

    // The charge lists have no button: charges only come from the rules (G-5B.3).
    foreach ([ListCustomers::class, ListPayments::class, ListCatalogServices::class, ListPackages::class] as $page) {
        $html = Livewire::test($page)->html();

        expect($html)->toContain('fi-ta-empty-state');
        expect(Str::after($html, 'fi-ta-empty-state'))->toContain('<button');
    }
});

it('shows the record count on the customer list, following the filters', function () {
    Customer::factory()->count(2)->create();
    Customer::factory()->create()->archive();

    Livewire::test(ListCustomers::class)
        ->assertSee(__('customers.count', ['count' => 2]))
        ->filterTable('archived', true)
        ->assertSee(__('customers.count', ['count' => 1]));
});

it('shows the overdue amounts in the danger token color and the other amounts in the text color', function () {
    $overdue = implode("\n", designRuleBodies('.fi-ta-ctn .fi-money .fi-ta-text-item.fi-color-danger'));
    $normal = implode("\n", designRuleBodies('.fi-ta-ctn .fi-ta-text-item'));

    expect($overdue)->toContain('var(--mt-danger-text)')
        ->and($normal)->toContain('var(--mt-text)');
});

it('renders the Today page tasks as a table with the shared table classes', function () {
    $this->get(Dashboard::getUrl())->assertOk();

    expect(designThemeCss())->toContain('.fi-today-tasks')
        ->and(designRuleBodies('.fi-today-tasks .fi-ta-cell'))->not->toBeEmpty();
});

it('draws the total row on the panel itself, not on Filament\'s gray footer (G-4B.6)', function () {
    expect(implode("\n", designRuleBodies('.fi-ta-ctn .fi-ta-table > tfoot')))
        ->toMatch('/background-color:\s*transparent;/');
});

it('keeps a free-service row\'s two Today buttons side by side only from xl, where the table still fits (G-4B.6)', function () {
    $xlBodies = collect(designRuleBodies('@media (min-width: 80rem)'))
        ->filter(fn (string $body): bool => str_contains($body, '.fi-today-tasks .fi-today-actions'));

    expect($xlBodies)->toHaveCount(1)
        ->and($xlBodies->first())->toMatch('/\.fi-today-tasks \.fi-today-actions\s*\{\s*white-space:\s*nowrap;\s*\}/');

    // Below xl the buttons may wrap: the only nowrap on the actions cell is the one above.
    $nowrapRules = collect(designRuleBodies('.fi-today-tasks .fi-today-actions'))
        ->filter(fn (string $body): bool => str_contains($body, 'nowrap'));

    expect($nowrapRules)->toHaveCount(1);
});

it('right-aligns the active service count on the customer list like the other numbers (G-4B.6)', function () {
    $column = Livewire::test(ListCustomers::class)->instance()->getTable()->getColumn('active_services_count');

    expect($column->getAlignment())->toBe(Alignment::End);
});
