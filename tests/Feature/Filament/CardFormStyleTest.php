<?php

use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CancelledCustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\TodayTasks;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Cards, forms, the form window and the button hierarchy (G-4B.5, T-84, docs/UI.md §4–6). The
 * visual match with the reference screens (docs/design/referans) is checked by eye; these tests pin
 * what can be pinned: colors only from tokens, one primary button per screen, dangerous buttons
 * never a solid red fill, the "Kapat" text on the form window, and the card's header strip and tables.
 */

/**
 * Selectors of the G-4B.5 rules in the theme CSS that set at least one color.
 *
 * @return array<int, string>
 */
function cardFormRuleSelectors(): array
{
    return [
        '.fi-btn:not(.fi-color):not(.fi-outlined)',
        '.fi-btn:not(.fi-color):not(.fi-outlined):not(.fi-disabled):not([disabled]):hover',
        '.fi-btn.fi-color-danger:not(.fi-outlined)',
        '.fi-btn.fi-outlined',
        '.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link',
        '.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link:hover',
        '.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link.fi-color-danger',
        '.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link.fi-color-danger:hover',
        '.fi-btn.fi-color.fi-color-danger:not(.fi-disabled):not([disabled]):hover',
        '.fi-section:not(.fi-section-not-contained):not(.fi-aside),',
        '.fi-section-header-heading',
        '.fi-section-header-description',
        '.fi-wi-stats-overview-stat-label',
        '.fi-wi-stats-overview-stat-value',
        '.fi-customer-header {',
        '.fi-customer-header-amount-label',
        '.fi-customer-header-amount-value',
        '.fi-fo-field-label-content',
        '.fi-fo-field-label-content .fi-fo-field-label-required-mark',
        '.fi-input-wrp',
        '.fi-input-wrp.fi-invalid',
        '.fi-input-wrp.fi-disabled',
        '.fi-fo-field-wrp-error-message',
        '.fi-modal > .fi-modal-window-ctn > .fi-modal-window',
        '.fi-modal > .fi-modal-window-ctn > .fi-modal-window.fi-mt-form-modal > .fi-modal-header,',
        '.fi-modal > .fi-modal-window-ctn > .fi-modal-window.fi-mt-form-modal .fi-modal-heading,',
        '.fi-modal > .fi-modal-window-ctn > .fi-modal-window.fi-mt-form-modal .fi-modal-description,',
        '.fi-modal > .fi-modal-window-ctn > .fi-modal-window.fi-mt-form-modal > .fi-modal-footer,',
        '.fi-modal-close-btn',
        '.fi-no-notification:not(.fi-inline),',
        '.fi-no-notification .fi-no-notification-title',
        '.fi-in-entry-label',
    ];
}

/**
 * Primary (navy, filled) buttons in a page's HTML: a `fi-btn` that carries `fi-color-primary`.
 */
function primaryButtonCount(string $html): int
{
    return (int) preg_match_all('/<(?:button|a)\b[^>]*\bclass="[^"]*\bfi-btn\b[^"]*\bfi-color-primary\b[^"]*"/', $html);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('takes the card, form and button colors only from tokens', function (string $selector) {
    // Selectors that end the rule list of a comma-separated rule are written without the comma here.
    $declarations = collect(designRuleBodies(rtrim($selector, ', {')))
        ->flatMap(designColorDeclarations(...))
        ->all();

    expect($declarations)->not->toBeEmpty("{$selector} sets no color.");

    foreach ($declarations as [$property, $value]) {
        expect($value)->toMatch(DESIGN_COLOR_VALUE, "{$selector} {$property}: {$value}");
    }
})->with(cardFormRuleSelectors());

it('draws the primary button from the blue tokens, with a darker hover in both themes and white text on the fill', function () {
    $body = implode("\n", designRuleBodies('.fi-btn.fi-color-primary:not(.fi-outlined)'));

    expect($body)->toContain('--bg: var(--mt-primary)')
        ->and($body)->toContain('--dark-bg: var(--mt-primary)')
        ->and($body)->toContain('--hover-bg: var(--mt-primary-hover)')
        ->and($body)->toContain('--dark-hover-bg: var(--mt-primary-hover)')
        ->and($body)->toContain('--text: var(--mt-on-primary)')
        ->and($body)->toContain('--dark-text: var(--mt-on-primary)');

    $light = designTokens('light');
    $dark = designTokens('dark');

    expect(designLuminance($light['mt-primary-hover']))->toBeLessThan(designLuminance($light['mt-primary']))
        ->and(designLuminance($dark['mt-primary-hover']))->toBeLessThan(designLuminance($dark['mt-primary']))
        ->and($dark['mt-primary-hover'])->toBe('#0a62c2')
        ->and($dark['mt-link'])->toBe('#5aa9ff');
});

it('never gives a dangerous button a solid red fill, filled or outlined, on hover too', function () {
    $bodies = [
        ...designRuleBodies('.fi-btn.fi-color-danger'),
        ...designRuleBodies('.fi-btn.fi-color-danger:not(.fi-outlined)'),
        ...designRuleBodies('.fi-btn.fi-outlined.fi-color-danger'),
        ...designRuleBodies('.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link.fi-color-danger'),
        ...designRuleBodies('.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link.fi-color-danger:hover'),
        ...designRuleBodies('.fi-btn.fi-color.fi-color-danger:not(.fi-disabled):not([disabled]):hover'),
    ];

    expect($bodies)->not->toBeEmpty();

    foreach ($bodies as $body) {
        // No fill from Filament's danger palette, only the panel zemin and the pale red badge fill (hover).
        expect($body)->not->toMatch('/var\(--(?:color|danger)-\d+\)/');

        preg_match_all('/(?<!-)(?:--(?:dark-)?(?:hover-)?bg|background(?:-color)?)\s*:\s*([^;]+);/', $body, $matches);

        foreach ($matches[1] as $value) {
            expect(trim($value))->toBeIn(['var(--mt-danger-bg)', 'var(--mt-badge-red-bg)']);
        }

        // Text and border come from the danger tokens (docs/UI.md §6).
        expect($body)->toMatch('/--mt-danger-(?:text|border)/');
    }
});

it('draws a dangerous button on hover with the pale red fill, a slight darkening and no outline, from the tokens, contrast-checked in both themes', function () {
    foreach ([
        '.fi-btn.fi-color.fi-color-danger:not(.fi-disabled):not([disabled]):hover',
        '.fi-ta-ctn .fi-ta-cell .fi-ac-link-action.fi-link.fi-color-danger:hover',
    ] as $selector) {
        $body = implode('
', designRuleBodies($selector));

        expect($body)->toContain('border-color: var(--mt-danger-border)')
            ->and($body)->toContain('background-color: var(--mt-badge-red-bg)')
            ->and($body)->toContain('filter: brightness(0.95)');
    }

    expect(implode('
', designRuleBodies('.fi-btn.fi-color-danger')))->toContain('--hover-bg: var(--mt-badge-red-bg)');

    foreach (['light', 'dark'] as $theme) {
        $tokens = designTokens($theme);

        // Text on the hover fill 4.5:1, the hover border against the panel 3:1 (WCAG 2).
        expect(designContrast($tokens['mt-danger-text'], $tokens['mt-badge-red-bg']))->toBeGreaterThanOrEqual(4.5)
            ->and(designContrast($tokens['mt-danger-text'], $tokens['mt-panel-bg']))->toBeGreaterThanOrEqual(3.0);
    }
});

it('draws a dangerous outlined button with the danger border token', function () {
    expect(implode("\n", designRuleBodies('.fi-btn.fi-outlined.fi-color-danger')))->toContain('border-color: var(--mt-danger-border)')
        ->and(implode("\n", designRuleBodies('.fi-btn.fi-color-danger:not(.fi-outlined)')))->toContain('border-color: var(--mt-danger-border)');
});

it('keeps one primary button on every screen: the "Ödeme al" of the top bar', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create();
    Charge::factory()->for($customer)->create();
    Payment::factory()->for($customer)->create();

    $urls = [
        'Bugün' => Dashboard::getUrl(),
        'Müşteriler' => CustomerResource::getUrl('index'),
        'Müşteri kartı' => CustomerResource::getUrl('view', ['record' => $customer]),
        'Açık borçlar' => ChargeResource::getUrl('index'),
        'Ödemeler' => PaymentResource::getUrl('index'),
        'Hizmetler' => CatalogServiceResource::getUrl('index'),
        'Paketler' => PackageResource::getUrl('index'),
        'Ödeme yöntemleri' => PaymentMethodResource::getUrl('index'),
    ];

    foreach ($urls as $screen => $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect(primaryButtonCount((string) $html))->toBe(1, "{$screen} has more or less than one primary button.");
    }
});

it('keeps one primary button, the "Kaydet", in the payment and the service panels: the page behind has none', function (string $action) {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create();
    Charge::factory()->for($customer)->create();

    $page = Livewire::test(ViewCustomer::class, ['record' => $customer->id])->mountAction($action);
    $mounted = $page->instance()->getMountedAction();

    // The Livewire test renders the panel's markup in a partial; the card behind it has no primary
    // button of its own, the panel's submit is the only one and "Vazgeç" is gray.
    expect(primaryButtonCount($page->html()))->toBe(0)
        ->and($mounted->getModalSubmitAction()->getColor())->toBe('primary')
        ->and($mounted->getModalSubmitAction()->getLabel())->toBe('Kaydet')
        ->and($mounted->getModalCancelAction()->getColor())->toBe('gray');
})->with(['recordPayment', 'assignService']);

it('shows the Today row buttons as secondary buttons, not primary ones', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => today()->subDays(3)]);
    CustomerService::factory()->for($customer)->free()->create();

    $widget = Livewire::test(TodayTasks::class);

    expect($widget->html())->toContain('fi-today-actions')
        ->and(primaryButtonCount($widget->html()))->toBe(0);

    foreach (['recordPaymentAction', 'removeFreeAction', 'confirmFreeContinuationAction'] as $method) {
        expect($widget->instance()->{$method}()->getColor())->toBe('gray');
    }
});

it('marks the delete and cancel buttons as danger, in the card and in the confirmation', function () {
    $customer = Customer::factory()->create();

    $page = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    expect($page->html())->toMatch('/<button\b[^>]*\bclass="[^"]*\bfi-btn\b[^"]*\bfi-color-danger\b/');

    $mounted = $page->mountAction(TestAction::make('delete')->schemaComponent('danger_actions'))->instance()->getMountedAction();

    expect($mounted->getModalSubmitAction()->getColor())->toBe('danger');
});

it('gives the form window a close button whose text is its own translated label', function () {
    // Filament renders the close button as an icon button with this label as its aria-label; the theme shows it as text.
    expect(__('filament::components/modal.actions.close.label'))->toBe('Kapat')
        ->and(implode("\n", designRuleBodies('.fi-modal-close-btn::after')))->toContain('content: attr(aria-label)');
});

it('opens forms in one centered window of the token width, with the panel title and ruled header and footer (T-84)', function () {
    // Decision change (T-84, docs/UI.md §2 Kural 8): this replaced the 440 px side panel (slide-over) rule.
    $window = '.fi-modal > .fi-modal-window-ctn > .fi-modal-window.fi-mt-form-modal';
    $header = implode("\n", designRuleBodies("{$window} > .fi-modal-header"));
    $footer = implode("\n", designRuleBodies("{$window} > .fi-modal-footer"));

    expect(designThemeCss())->toContain('--mt-modal-width: 640px;')
        ->not->toContain('--mt-slideover-width')
        ->not->toContain('fi-modal-slide-over')
        ->and(implode("\n", designRuleBodies($window)))->toContain('max-width: var(--mt-modal-width)')
        ->and(implode("\n", designRuleBodies("{$window} .fi-modal-heading")))->toContain('font-size: var(--mt-text-panel-title)')->toContain('font-weight: 700')
        ->and($header)->toContain('border-bottom: var(--mt-border-width) solid')->toContain('background-color: var(--mt-panel-bg)')
        ->and($footer)->toContain('border-top: var(--mt-border-width) solid')->toContain('background-color: var(--mt-panel-bg)');
});

it('keeps a focused field clear of the sticky bars, and lets the bars scroll with the form on a very short screen (T-84)', function () {
    // Measured in Chrome at 375×400 and 375×300 (a phone with its keyboard open): no field under either bar.
    $window = '.fi-modal > .fi-modal-window-ctn > .fi-modal-window.fi-mt-form-modal';
    $css = (string) preg_replace('#/\*.*?\*/#s', '', designThemeCss());

    expect(implode("\n", designRuleBodies($window)))->toContain('scroll-padding-block: 64px 88px')
        ->toContain('scroll-padding-block: 0')
        ->and(implode("\n", designRuleBodies("{$window} > .fi-modal-header")))->toContain('position: static')
        ->and(implode("\n", designRuleBodies("{$window} > .fi-modal-footer")))->toContain('position: static')
        ->and($css)->toMatch('/@media \(max-height: 24rem\) \{\s*\.fi-modal > \.fi-modal-window-ctn > \.fi-modal-window\.fi-mt-form-modal,/');
});

it('gives form fields the token focus ring and an invalid field the danger border', function () {
    $focus = implode("\n", designRuleBodies('.fi-input-wrp:not(.fi-disabled):not(:has(.fi-ac-action:focus)):focus-within'));

    expect($focus)->toContain('box-shadow: none')
        ->and($focus)->toContain('var(--mt-focus-width)')
        ->and($focus)->toContain('var(--mt-focus-color)')
        ->and(implode("\n", designRuleBodies('.fi-input-wrp')))->toContain('box-shadow: none')
        ->and(implode("\n", designRuleBodies('.fi-input-wrp.fi-invalid')))->toContain('border-color: var(--mt-danger-text)')
        ->and(implode("\n", designRuleBodies('.fi-fo-field-wrp-error-message')))->toContain('var(--mt-danger-text)');
});

it('shows the card header as one strip: name, legal title, the amounts and the two actions', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Logi Bilişim Ltd. Şti.']);
    Charge::factory()->for($customer)->create(['amount' => 190000, 'currency' => Currency::Try]);
    Payment::factory()->for($customer)->create(['amount' => 2500, 'currency' => Currency::Usd]);

    $page = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    $page->assertSeeHtmlInOrder(['fi-customer-header', 'Müşteriler', 'Logi', 'Logi Bilişim Ltd. Şti.', 'Açık borç', '1.900 TL', 'Alacak bakiyesi', '25 USD', 'Ödeme al', 'Hizmet ekle']);

    expect($page->html())->not->toContain('Görüntüle');
});

it('shows the active service count in the Hizmetler heading, but not in the cancelled table', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->count(2)->for($customer)->create();
    CustomerService::factory()->for($customer)->cancelled()->create();

    $active = Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class]);

    expect($active->instance()->getTable()->getDescription())->toBe('2 aktif');

    $cancelled = Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class]);

    expect($cancelled->instance()->getTable()->getDescription())->toBeNull();
});

it('shows the service status as its own column from xl, and as a badge under the name below xl', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();
    $endingSoon = CustomerService::factory()->for($customer)->create(['start_date' => '2025-06-10', 'end_date' => '2026-06-20']);
    $plain = CustomerService::factory()->for($customer)->create(['start_date' => '2025-12-10', 'end_date' => '2026-12-10']);
    $endingSoon->items()->delete();
    $plain->items()->delete();

    $table = Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable();

    expect($table->getColumn('status')->getVisibleFrom())->toBe('xl');

    $name = $table->getColumn('name');

    expect($name->record($endingSoon)->getDescriptionBelow()->toHtml())->toContain('fi-badge-below-xl')
        ->and($name->record($plain)->getDescriptionBelow())->toBeNull()
        ->and($endingSoon->status)->toBe(CustomerServiceStatus::Active);
});

it('shows the charge status as its own column from xl, and under "Kalan" below xl', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();
    $overdue = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-05']);
    $plain = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-07-30']);

    $table = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable();

    $status = $table->getColumn('status');

    expect($status->getVisibleFrom())->toBe('xl')
        ->and($table->getColumn('due_date')->getVisibleFrom())->toBe('xl')
        ->and($status->record($overdue)->getState())->toBe('Gecikmiş (5 gün)')
        ->and($status->record($plain)->getState())->toBeNull()
        ->and($table->getColumn('remaining')->record($overdue)->getDescriptionBelow()->toHtml())->toContain('fi-badge-below-xl')
        ->and($table->getColumn('remaining')->record($plain)->getDescriptionBelow())->toBeNull();
});
