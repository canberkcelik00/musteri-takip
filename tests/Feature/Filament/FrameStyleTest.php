<?php

use App\Filament\Actions\CreateCustomerAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

/*
 * Frame: sidebar, topbar and page header (G-4B.3, docs/UI.md §3–4 and §6). The visual match with
 * the reference screens (docs/design/referans) is checked by eye; these tests pin what can be
 * pinned: the same frame on every page, colors only from tokens, the layout hooks the CSS relies on.
 */

/**
 * Selectors of the frame rules in the theme CSS (each one sets at least one color).
 *
 * @return array<int, string>
 */
function frameRuleSelectors(): array
{
    return [
        '.fi-body',
        '.fi-body .fi-sidebar',
        '.fi-body .fi-sidebar .fi-sidebar-header',
        '.fi-sidebar .fi-logo',
        '.fi-brand-mark',
        '.fi-sidebar-group-label',
        '.fi-sidebar-group-collapse-btn',
        '.fi-sidebar-item-label',
        '.fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover',
        '.fi-sidebar-item.fi-active > .fi-sidebar-item-btn',
        '.fi-topbar',
        '.fi-global-search-field .fi-input-wrp',
        '.fi-global-search-field .fi-input::placeholder',
        '.fi-global-search-field .fi-input-wrp-suffix .fi-input-wrp-label',
        '.fi-header-heading',
        '.fi-header-subheading',
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('renders the same frame on every menu page: topbar, sidebar with logo and menu, page heading', function () {
    $customer = Customer::factory()->create();

    $urls = [
        'Bugün' => Dashboard::getUrl(),
        'Müşteriler' => CustomerResource::getUrl('index'),
        'Müşteri kartı' => CustomerResource::getUrl('view', ['record' => $customer]),
        'Açık borçlar' => ChargeResource::getUrl('index'),
        'Ödemeler' => PaymentResource::getUrl('index'),
        'Hizmetler' => CatalogServiceResource::getUrl('index'),
        'Paketler' => PackageResource::getUrl('index'),
        'Ödeme yöntemleri' => PaymentMethodResource::getUrl('index'),
        'Genel ayarlar' => ManageSettings::getUrl(),
    ];

    foreach ($urls as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSeeInOrder([
                'fi-global-search-ctn',
                'data-topbar-actions',
                'fi-sidebar-header',
                'fi-brand-mark',
                'Müşteri Takip',
                'fi-sidebar-nav',
                'fi-header-heading',
            ], escape: false);
    }
});

it('has a rule for every frame selector', function (string $selector) {
    expect(designRuleBodies($selector))->not->toBeEmpty("No rule for {$selector} in the theme CSS.");
})->with(frameRuleSelectors());

it('takes frame colors only from tokens', function (string $selector) {
    $declarations = collect(designRuleBodies($selector))
        ->flatMap(designColorDeclarations(...))
        ->all();

    expect($declarations)->not->toBeEmpty("{$selector} sets no color.");

    foreach ($declarations as [$property, $value]) {
        expect($value)->toMatch(DESIGN_COLOR_VALUE, "{$selector} {$property}: {$value}");
    }
})->with(frameRuleSelectors());

it('rejects a literal color in a frame rule', function () {
    // The allow-list itself: anything but a token or one of the keywords must fail it.
    foreach (['#fff', '#0f1d33', 'white', 'rgb(0 0 0)', 'oklch(0.5 0 0)', 'var(--color-gray-500)', 'var(--tw-ring-color)'] as $value) {
        expect($value)->not->toMatch(DESIGN_COLOR_VALUE);
    }

    foreach (['var(--mt-border)', 'transparent', 'none', 'inherit', 'currentColor'] as $value) {
        expect($value)->toMatch(DESIGN_COLOR_VALUE);
    }
});

it('pushes the settings group to the bottom of the sidebar and keeps a long menu scrollable', function () {
    $this->get(Dashboard::getUrl())->assertOk();

    // The CSS pushes the last group down, so the last group must be "Ayarlar" (docs/UI.md §3).
    expect(collect(Filament::getNavigation())->last()->getLabel())->toBe('Ayarlar');

    $lastGroup = implode("\n", designRuleBodies('.fi-sidebar-nav-groups > .fi-sidebar-group:last-child'));
    $nav = implode("\n", designRuleBodies('.fi-sidebar-nav'));

    expect($lastGroup)->toContain('margin-top: auto')
        ->and($nav)->toContain('flex-col')
        ->and($nav)->toContain('flex-1')
        ->and($nav)->toContain('min-h-0')
        ->and($nav)->toContain('overflow-y-auto');
});

it('draws the focus rings with the focus tokens: sidebar color in the menu, the field color in the search box', function () {
    $menu = implode("\n", designRuleBodies('.fi-sidebar-item-btn:focus-visible'));
    $search = implode("\n", designRuleBodies('.fi-global-search-field .fi-input-wrp:not(.fi-disabled):not(:has(.fi-ac-action:focus)):focus-within'));

    expect($menu)->toContain('var(--mt-sidebar-focus)')
        ->and($menu)->toContain('var(--mt-focus-width)')
        ->and($menu)->not->toContain('--mt-focus-color')
        ->and($search)->toContain('var(--mt-focus-color)')
        ->and($search)->toContain('var(--mt-focus-width)')
        ->and($search)->not->toContain('--mt-sidebar-focus');
});

it('keeps the logo free of literal colors and the brand color on a token', function () {
    $view = (string) file_get_contents(resource_path('views/filament/panel/brand.blade.php'));

    expect($view)->toContain('fi-brand-mark')
        ->and($view)->not->toMatch('/#[0-9a-f]{3,8}\b/i')
        ->and($view)->not->toMatch('/\b(rgba?|hsla?|oklch)\(/i');

    $declarations = collect(designRuleBodies('.fi-brand-mark'))->flatMap(designColorDeclarations(...))->all();

    expect($declarations)->toContain(['background-color', 'var(--mt-brand)']);

    $this->get(Dashboard::getUrl())->assertSee('MT');
});

it('sets the sidebar to 272 px and keeps it from collapsing on the desktop', function () {
    $panel = Filament::getCurrentPanel();

    // A collapsible sidebar would change its width, and the topbar's left margin follows the fixed one.
    expect($panel->getSidebarWidth())->toBe('17rem')
        ->and($panel->isSidebarCollapsibleOnDesktop())->toBeFalse()
        ->and($panel->isSidebarFullyCollapsibleOnDesktop())->toBeFalse();
});

it('shows an icon beside every menu item, in the muted menu color and white when active', function () {
    $icon = implode("\n", designRuleBodies('.fi-sidebar-item-icon'));
    $active = implode("\n", designRuleBodies('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-icon'));

    expect($icon)->not->toContain('display: none')
        ->and($icon)->toContain('var(--mt-sidebar-group)')
        ->and($active)->toContain('var(--mt-sidebar-active-text)');
});

it('gives every menu item its own icon', function () {
    $this->get(Dashboard::getUrl())->assertOk();

    $icons = collect(Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getIcon());

    expect($icons->every(fn ($icon) => filled($icon)))->toBeTrue()
        ->and($icons->unique()->count())->toBe($icons->count());
});

it('shows today\'s date under the "Bugün" title, in Istanbul time', function (Carbon $now) {
    $this->travelTo($now);

    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('1 Ekim 2026, Perşembe');
})->with([
    'noon in Istanbul' => fn () => Carbon::parse('2026-10-01 12:00', 'Europe/Istanbul'),
    'Istanbul is already the 1st when UTC is not' => fn () => Carbon::parse('2026-09-30 22:30', 'UTC'),
]);

it('lets the search box shrink on a phone and keeps the user menu and "Ödeme al" at their size', function () {
    // The search box is a flex item whose minimum width is its 380 px field; without
    // `min-width: 0` it ran under "Ödeme al" at 375 px (G-4B.3 follow-up).
    expect(implode("\n", designRuleBodies('.fi-global-search')))->toContain('min-w-0')
        ->and(implode("\n", designRuleBodies('.fi-global-search-ctn')))->toContain('min-w-0')
        ->and(implode("\n", designRuleBodies('.fi-topbar-end > .fi-dropdown')))->toContain('shrink-0')
        ->and(implode("\n", designRuleBodies('.fi-topbar-actions')))->toContain('shrink-0');

    // Only the key hint goes below `sm`, never the label of "Ödeme al" (docs/UI.md §2 Kural 5).
    expect(designThemeCss())->toMatch('/not all and \(min-width: 40rem\)\s*\{\s*\.fi-global-search-field \.fi-input-wrp-suffix\s*\{\s*display:\s*none;/');
});

it('shows "Ödeme al" in the topbar with its label, not as an icon-only button', function () {
    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['data-topbar-actions', __('payments.record')]);

    expect(designThemeCss())->not->toMatch('/\.fi-topbar[^{]*\.fi-(btn|link)-label[^{]*\{[^}]*display:\s*none/');
});

it('hides the "… > Liste" trail on list pages only; the customer card keeps its own trail (G-4B.6)', function () {
    expect(implode("\n", designRuleBodies('.fi-resource-list-records-page .fi-header .fi-breadcrumbs')))
        ->toMatch('/display:\s*none;/');

    $this->get(ChargeResource::getUrl('index'))->assertOk()->assertSee('fi-resource-list-records-page', false);

    $customer = Customer::factory()->create();
    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
        ->assertOk()
        ->assertDontSee('fi-resource-list-records-page', false)
        ->assertSee('fi-breadcrumbs', false);
});

it('draws "Ödeme al" and "Yeni müşteri" as text buttons without a "+" icon (G-4B.6)', function () {
    expect(RecordPaymentAction::make()->getIcon())->toBeNull()
        ->and(CreateCustomerAction::make()->getIcon())->toBeNull();

    $customer = Customer::factory()->create();
    $searchActions = CustomerResource::getGlobalSearchResultActions($customer);

    expect($searchActions)->not->toBeEmpty()
        ->and(collect($searchActions)->map(fn (Action $action): mixed => $action->getIcon())->filter()->all())->toBeEmpty();
});

it('shows the topbar\'s "Yeni müşteri" button with its label (G-5B.3)', function () {
    expect(designThemeCss())->not->toMatch('/\.fi-topbar-new-action[^{]*\.fi-btn-label[^{]*\{[^}]*display:\s*none/');

    $this->get(Dashboard::getUrl())->assertOk()->assertSeeInOrder(['fi-topbar-new-action', __('navigation.new_customer')], false);
});
