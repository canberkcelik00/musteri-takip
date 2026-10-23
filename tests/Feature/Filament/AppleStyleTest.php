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
use Filament\Facades\Filament;
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
