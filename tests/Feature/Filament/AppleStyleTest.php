<?php

use App\Filament\Actions\WhatsAppReminderAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Widgets\TodayTasks;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use App\Support\Avatar;
use App\Support\AvatarProviders\InitialsAvatarProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Livewire;

/*
 * Faz 4C, Apple style (T-130, docs/UI.md §2 Kural 5, §3, §6): the bottom tab bar of narrow screens,
 * the customer avatars, the icon-only contact actions, and the contrast of the new colors.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('renders the tab bar on every panel page with the three daily screens and the menu', function () {
    $customer = Customer::factory()->create();

    foreach ([Dashboard::getUrl(), CustomerResource::getUrl('index'), CustomerResource::getUrl('view', ['record' => $customer]), ChargeResource::getUrl('index')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('class="fi-tab-bar"', false)
            ->assertSee('aria-label="Ana gezinme"', false)
            ->assertSee('href="'.Dashboard::getUrl().'"', false)
            ->assertSee('href="'.CustomerResource::getUrl('index').'"', false)
            ->assertSee('href="'.ChargeResource::getUrl('index').'"', false)
            ->assertSeeInOrder(['Bugün', 'Müşteriler', 'Borçlar', 'Menü']);
    }
});

it('marks only the current screen\'s tab', function (string $url, string $label) {
    $html = $this->get($url)->assertOk()->getContent();

    preg_match('/<nav class="fi-tab-bar".*?<\/nav>/s', $html, $bar);

    expect(substr_count($bar[0], 'aria-current="page"'))->toBe(1)
        ->and($bar[0])->toMatch('/aria-current="page"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<span>'.$label.'<\/span>/s');
})->with([
    'Bugün' => fn () => [Dashboard::getUrl(), 'Bugün'],
    'Müşteriler' => fn () => [CustomerResource::getUrl('index'), 'Müşteriler'],
    'Borçlar' => fn () => [ChargeResource::getUrl('index'), 'Borçlar'],
]);

it('has no tab bar for a visitor who is not signed in', function () {
    auth()->logout();

    $this->get('/panel/login')->assertOk()->assertDontSee('fi-tab-bar', false);
});

it('hides the tab bar from lg up and shows it below, in place of the hamburger', function () {
    $rules = designRuleBodies('.fi-tab-bar');

    expect($rules[0])->toContain('display: none')
        ->and(implode("\n", $rules))->toContain('position: fixed')
        ->and(implode("\n", designRuleBodies('.fi-topbar-open-sidebar-btn')))->toContain('display: none');
});

it('uses no filter, backdrop filter or transform on the bars, so nothing repaints on scroll and the action windows rendered inside the topbar are not trapped (T-132)', function () {
    $css = (string) preg_replace('#/\*.*?\*/#s', '', designThemeCss());

    // A backdrop filter repaints the page on every scroll; on the topbar it also became the containing
    // block of the "Ödeme al" and "Yeni müşteri" windows Filament renders inside it.
    expect($css)->not->toMatch('/(?<![-\w])(backdrop-filter|-webkit-backdrop-filter|filter)\s*:/');

    foreach (['.fi-topbar', '.fi-topbar-ctn', '.fi-topbar-end', '.fi-topbar-actions', '.fi-global-search-ctn'] as $selector) {
        expect(implode("\n", designRuleBodies($selector)))
            ->not->toMatch('/(?<![-\w])(transform|perspective|contain|will-change)\s*:/', "{$selector} would trap its fixed descendants");
    }
});

it('paints the selected menu row blue with white text, the hover row gray, and the squares translucent when selected', function () {
    $active = implode('
', designRuleBodies('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn'));
    $hover = implode('
', designRuleBodies('.fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover'));
    $square = implode('
', designRuleBodies('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-icon'));

    expect($active)->toContain('background-color: var(--mt-sidebar-active-bg)')
        ->and($active)->toContain('color: var(--mt-sidebar-active-text)')
        ->and($hover)->toContain('background-color: var(--mt-sidebar-hover-bg)')
        ->and($square)->toContain('var(--mt-nav-on-active)');
});

it('draws the switch green when on and gray when off, the check boxes and the current page blue', function () {
    expect(implode('
', designRuleBodies('.fi-toggle.fi-toggle-on')))->toContain('var(--mt-green)')
        ->and(implode('
', designRuleBodies('.fi-toggle.fi-toggle-off')))->toContain('var(--mt-row-divider)')
        ->and(designThemeCss())->toContain('.fi-pagination .fi-pagination-item.fi-active .fi-pagination-item-btn')
        ->and(designThemeCss())->toContain("input[type='checkbox'].fi-checkbox-input:checked");

    foreach (['light', 'dark'] as $theme) {
        $tokens = designTokens($theme);

        expect(designContrast($tokens['mt-green'], $tokens['mt-panel-bg']))->toBeGreaterThanOrEqual(1.5);
    }
});

it('gives the avatar two letters, the Turkish way, and a stable color', function (string $name, string $initials) {
    expect(Avatar::initials($name))->toBe($initials);
})->with([
    'two words' => ['İzmir Ofis', 'İO'],
    'lowercase i' => ['izmir ofis', 'İO'],
    'dotless i' => ['ışık lojistik', 'IL'],
    'one word' => ['Logi', 'LO'],
    'one letter' => ['A', 'A'],
    'digit first' => ['3M Türkiye', '3T'],
    'extra spaces' => ["  Deniz   Lojistik \n", 'DL'],
    'empty' => ['   ', '?'],
]);

it('keeps the avatar color of the same customer fixed and cycles through eight', function () {
    expect(Avatar::colorNumber(1))->toBe(Avatar::colorNumber(1))
        ->and(Avatar::colorNumber(8))->toBe(1)
        ->and(Avatar::colorNumber(9))->toBe(2)
        ->and(collect(range(1, 8))->map(Avatar::colorNumber(...))->unique()->count())->toBe(8);
});

it('draws the avatar beside the customer on the customer list, the charge list, the Today page and the card', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    Charge::factory()->for($customer)->create(['due_date' => today()->subDays(3)]);
    $number = Avatar::colorNumber($customer->id);

    Livewire::test(ListCustomers::class)
        ->assertSeeHtml('data-initials="LO"')
        ->assertSeeHtml('fi-mt-avatar-cell fi-mt-avatar-'.$number);

    Livewire::test(ListCharges::class)
        ->assertSeeHtml('data-initials="LO"');

    Livewire::test(TodayTasks::class)
        ->assertSeeHtml('class="fi-mt-avatar fi-mt-avatar-'.$number.'"');

    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
        ->assertOk()
        ->assertSee('class="fi-mt-avatar fi-mt-avatar-'.$number.'"', false);

});

it('keeps the avatar decorative: hidden from screen readers and without its own text in the cell', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    $html = view('filament.support.avatar', ['name' => $customer->short_name, 'id' => $customer->id])->render();

    expect($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('LO');

    $cell = implode("\n", designRuleBodies('.fi-mt-avatar-cell'));

    expect($cell)->toContain("content: attr(data-initials) / ''");
});

it('draws every avatar and menu square from a token, with readable white letters and glyphs', function (string $theme) {
    $tokens = designTokens($theme);

    foreach (range(1, 8) as $number) {
        expect($tokens)->toHaveKey("mt-avatar-{$number}")
            ->and(designContrast($tokens['mt-on-brand'], $tokens["mt-avatar-{$number}"]))->toBeGreaterThanOrEqual(4.5, "avatar {$number}");
    }

    foreach (['blue', 'indigo', 'orange', 'purple', 'green', 'teal', 'gray'] as $color) {
        expect($tokens)->toHaveKey("mt-nav-{$color}")
            ->and(designContrast($tokens['mt-on-brand'], $tokens["mt-nav-{$color}"]))->toBeGreaterThanOrEqual(3.0, "menu square {$color}");
    }
})->with(['light', 'dark']);

it('makes the WhatsApp reminder an icon button with the translated name as its tooltip and label', function () {
    $action = WhatsAppReminderAction::make();

    expect($action->isIconButton())->toBeTrue()
        ->and($action->getLabel())->toBe('WhatsApp')
        ->and($action->getTooltip())->toBe('WhatsApp')
        ->and($action->getIcon())->toBeInstanceOf(Htmlable::class)
        ->and($action->getExtraAttributes()['class'])->toContain('fi-whatsapp-action');

    expect(view('filament.icons.whatsapp')->render())->toContain('aria-hidden="true"')->toContain('<svg');
});

it('shows a phone and an e-mail icon beside the contact\'s number and address, both still links', function () {
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->for($customer)->create(['phone' => '0532 000 00 00', 'email' => 'ali@logi.com.tr']);

    $table = Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable();

    expect($table->getColumn('phone')->getIcon('x'))->toBe(Heroicon::OutlinedPhone)
        ->and($table->getColumn('email')->getIcon('x'))->toBe(Heroicon::OutlinedEnvelope)
        ->and($table->getColumn('phone')->record($contact)->getUrl())->toBe('tel:0532 000 00 00')
        ->and($table->getColumn('email')->record($contact)->getUrl())->toBe('mailto:ali@logi.com.tr');
});

it('centers a view used as an icon: the svg in its wrapper is a block filling it, so the WhatsApp glyph sits level with the buttons beside it (T-133)', function () {
    $rules = implode("\n", designRuleBodies('.fi-icon > svg'));

    expect($rules)->toContain('display: block')
        ->and($rules)->toContain('width: 100%')
        ->and($rules)->toContain('height: 100%')
        ->and(view('filament.icons.whatsapp')->render())->toContain('width="100%"');
});

it('never opens the system\'s own list: every select is Filament\'s, and its list takes the menu look (T-133)', function () {
    $select = Select::make('type');

    expect($select->isNative())->toBeFalse();

    $option = implode("\n", designRuleBodies('.fi-select-input .fi-select-input-option'));

    expect($option)->toContain('border-radius: 8px')
        ->and($option)->toContain('var(--mt-text)')
        ->and(implode("\n", designRuleBodies('.fi-select-input .fi-select-input-btn')))->toContain('background-image: none');
});

it('gives the user menu a gray initials avatar drawn inline, with no avatar service', function () {
    $user = User::factory()->create(['name' => 'Yasin Çelik']);

    $uri = (new InitialsAvatarProvider)->get($user);
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and($svg)->toContain('>YÇ</text>')
        ->and($svg)->toContain(InitialsAvatarProvider::GRADIENT_TOP)
        ->and($uri)->not->toContain('ui-avatars');

    $this->get(Dashboard::getUrl())->assertOk()->assertDontSee('ui-avatars.com', false);

    // White letters must read on the lighter end of the gradient (large, semibold text: 3:1).
    expect(designContrast('#ffffff', InitialsAvatarProvider::GRADIENT_TOP))->toBeGreaterThanOrEqual(2.2);
});

it('draws the tabs as a segmented control, the clickable row\'s chevron as hidden decoration and the tooltip dark in both themes', function () {
    $tabs = implode("\n", designRuleBodies('.fi-tabs:not(.fi-vertical)'));
    $active = implode("\n", designRuleBodies('.fi-tabs .fi-tabs-item.fi-active'));
    $chevron = implode("\n", designRuleBodies('.fi-ta-ctn .fi-ta-row.fi-clickable > td:last-child::after'));

    expect($tabs)->toContain('var(--mt-field-bg)')
        ->and($active)->toContain('var(--mt-segment-active-bg)')
        ->and($chevron)->toContain("content: '›' / ''");

    foreach (['light', 'dark'] as $theme) {
        $tokens = designTokens($theme);

        expect(designContrast($tokens['mt-text'], $tokens['mt-segment-active-bg']))->toBeGreaterThanOrEqual(4.5, "segment {$theme}")
            ->and(designContrast($tokens['mt-tooltip-text'], $tokens['mt-tooltip-bg']))->toBeGreaterThanOrEqual(4.5, "tooltip {$theme}")
            ->and(designContrast($tokens['mt-text-tertiary'], $tokens['mt-panel-bg']))->toBeGreaterThanOrEqual(3.0, "chevron {$theme}");
    }
});

it('opens the search results from the field\'s left edge and highlights the row blue, with a soft focus halo on fields (T-133)', function () {
    $panel = implode("\n", designRuleBodies('.fi-topbar .fi-global-search-results-ctn'));
    $row = implode("\n", designRuleBodies('.fi-global-search-result:hover, .fi-global-search-result:focus-within'));

    expect($panel)->toContain('inset-inline: 0 auto')
        ->and(designThemeCss())->toContain('--mt-focus-ring')
        ->and(designThemeCss())->toContain('.fi-global-search-result:hover')
        ->and($row === '' || str_contains($row, 'var(--mt-primary)'))->toBeTrue();
});

it('puts the buttons of a row on one center line: no negative margin on the icon button, a flex row on Bugün (T-133)', function () {
    $icon = implode("\n", designRuleBodies('.fi-ta-actions > .fi-icon-btn'));
    $row = implode("\n", designRuleBodies('.fi-today-tasks .fi-today-actions-row'));

    expect($icon)->toContain('margin: 0')
        ->and($icon)->toContain('align-self: center')
        ->and($row)->toContain('display: flex')
        ->and($row)->toContain('align-items: center');

    $html = Livewire::test(TodayTasks::class)->html();
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => today()->subDays(3)]);

    expect(Livewire::test(TodayTasks::class)->html())->toContain('class="fi-today-actions-row"');
});
