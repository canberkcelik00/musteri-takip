<?php

use App\Enums\Currency;
use App\Livewire\WindowHost;
use App\Models\User;
use App\Support\Ui\Navigation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\Fixtures\Ui\ExampleWindow;

it('draws a badge with one of four tones', function () {
    expect(Blade::render('<x-ui.badge tone="red">Gecikmiş</x-ui.badge>'))
        ->toContain('ui-badge ui-badge-red')->toContain('Gecikmiş');

    expect(Blade::render('<x-ui.badge tone="green">Ödendi</x-ui.badge>'))->toContain('ui-badge-green');
    expect(Blade::render('<x-ui.badge tone="amber">Yaklaşıyor</x-ui.badge>'))->toContain('ui-badge-amber');
});

it('draws any other badge tone gray', function () {
    $html = Blade::render('<x-ui.badge tone="purple">Taslak</x-ui.badge>');

    expect($html)->toContain('class="ui-badge"')->not->toContain('purple');
    expect(Blade::render('<x-ui.badge>Pasif</x-ui.badge>'))->toContain('class="ui-badge"');
});

it('draws an avatar with the Turkish initials and a stable color', function () {
    $html = Blade::render('<x-ui.avatar name="işık ağaç" :id="3" />');

    expect($html)->toContain('İA')->toContain('ui-avatar-4')->toContain('aria-hidden="true"');
});

it('formats money from minor units with the Money helper', function () {
    expect(Blade::render('<x-ui.money :amount="400050" :currency="$currency" />', ['currency' => Currency::Try]))
        ->toContain('4.000,50 ');

    expect(Blade::render('<x-ui.money :amount="1200000" currency="USD" :danger="true" />'))
        ->toContain('12.000')->toContain('USD')->toContain('ui-money-danger');
});

it('draws a primary button, a danger button and a link button', function () {
    expect(Blade::render('<x-ui.button variant="primary">Kaydet</x-ui.button>'))
        ->toContain('<button type="button"')->toContain('ui-btn-primary');

    expect(Blade::render('<x-ui.button variant="danger" type="submit">Sil</x-ui.button>'))
        ->toContain('type="submit"')->toContain('ui-btn-danger');

    expect(Blade::render('<x-ui.button href="/app">Git</x-ui.button>'))
        ->toContain('<a href="/app"')->not->toContain('<button');
});

it('puts a field error under the input of its wire:model property', function () {
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['name' => ['Ad zorunludur.']]));

    view()->share('errors', $errors);

    $html = Blade::render('<x-ui.input label="Ad" wire:model="name" />');

    expect($html)->toContain('Ad zorunludur.')->toContain('ui-error')->toContain('<label');
});

it('draws selects as an Alpine listbox with grouped options, never a native select', function () {
    view()->share('errors', new ViewErrorBag);

    $html = Blade::render('<x-ui.select wire:model="plan" :options="$options" :searchable="true" />', [
        'options' => ['a' => 'Aylık', 'Yıllık' => ['b' => 'Yıl 1', 'c' => 'Yıl 2']],
    ]);

    expect($html)
        ->not->toContain('<select')
        ->toContain('role="listbox"')
        ->toContain('x-modelable="value"')
        ->toContain('wire:model="plan"')
        ->toContain('Aylık')
        ->toContain('Yıl 2')
        ->toContain('Yıllık');
});

it('draws a toggle and a segmented control that bind with wire:model', function () {
    expect(Blade::render('<x-ui.toggle wire:model="active" label="Aktif" />'))
        ->toContain('role="switch"')->toContain('wire:model="active"')->toContain('Aktif');

    expect(Blade::render('<x-ui.segmented wire:model="theme" :options="[\'light\' => \'Açık\', \'dark\' => \'Koyu\']" />'))
        ->toContain('role="radiogroup"')->toContain('wire:model="theme"')->toContain('Koyu');
});

it('draws a window with a title and a footer', function () {
    $html = Blade::render('<x-ui.window title="Ödeme al">İçerik<x-slot:footer>Altlık</x-slot:footer></x-ui.window>');

    expect($html)->toContain('role="dialog"')->toContain('Ödeme al')->toContain('İçerik')->toContain('Altlık');
});

it('opens a registered window with its parameters and closes it', function () {
    config()->set('ui.windows.example', ExampleWindow::class);

    Livewire::test(WindowHost::class)
        ->assertDontSee('Deneme penceresi')
        ->dispatch('open-window', name: 'example', params: ['subject' => 'Merhaba dünya'])
        ->assertSee('Deneme penceresi')
        ->assertSee('Merhaba dünya')
        ->dispatch('close-window')
        ->assertDontSee('Deneme penceresi')
        ->assertSet('name', null);
});

it('opens nothing for a window name that is not registered', function () {
    config()->set('ui.windows.example', ExampleWindow::class);

    Livewire::test(WindowHost::class)
        ->dispatch('open-window', name: 'nope')
        ->assertSet('name', null)
        ->assertSet('opened', 0);
});

it('gives each opening a fresh window', function () {
    config()->set('ui.windows.example', ExampleWindow::class);

    Livewire::test(WindowHost::class)
        ->dispatch('open-window', name: 'example', params: ['subject' => 'Bir'])
        ->dispatch('open-window', name: 'example', params: ['subject' => 'İki'])
        ->assertSet('opened', 2)
        ->assertSee('İki')
        ->assertDontSee('Bir');
});

it('does not let the browser set which window is open', function () {
    config()->set('ui.windows.example', ExampleWindow::class);

    Livewire::test(WindowHost::class)->set('name', 'example');
})->throws('Cannot update locked property');

it('draws the windows after the top bar, outside every bar, and a notification area', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('app.today'))->assertOk()->getContent();

    $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));

    expect($header)->not->toContain('window-host')
        ->and(strpos($html, 'window-host'))->toBeGreaterThan(strpos($html, '</header>'))
        ->and($html)->toContain('ui-toasts');
});

it('applies the saved theme before the first paint', function () {
    $html = $this->get(route('app.login'))->getContent();

    expect($html)->toContain("localStorage.getItem('mt-theme')")->toContain("localStorage.getItem('mt-glass')");
});

it('links sidebar items to the glass interface when the screen exists and to the panel until then', function () {
    $entries = Navigation::entries(route('app.today'));

    expect($entries[0])->toMatchArray(['url' => route('app.today'), 'active' => true])
        ->and($entries[1])->toMatchArray(['url' => route('app.customers.index'), 'active' => false]);
});

it('marks the current item and opens the catalog group only when it holds the current page', function () {
    $closed = collect(Navigation::entries(route('app.today')))->firstWhere('group', 'ui.nav.catalog');
    $open = collect(Navigation::entries(route('app.catalog.packages')))->firstWhere('group', 'ui.nav.catalog');

    expect($closed['open'])->toBeFalse()
        ->and($open['open'])->toBeTrue()
        ->and(collect($open['items'])->firstWhere('active', true)['label'])->toBe('ui.nav.packages');
});

it('keeps the settings and collections groups open and the bottom tabs to the three daily screens', function () {
    $entries = collect(Navigation::entries(route('app.today')));

    expect($entries->firstWhere('group', 'ui.nav.collections')['open'])->toBeTrue()
        ->and($entries->firstWhere('group', 'ui.nav.settings')['open'])->toBeTrue()
        ->and(collect(Navigation::tabs(route('app.today')))->pluck('tab')->all())
        ->toBe(['ui.nav.tab_today', 'ui.nav.tab_customers', 'ui.nav.tab_charges']);
});

it('renders the sidebar with the menu squares and a "Menü" tab', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('app.today'))
        ->assertOk()
        ->assertSee('ui-nav-blue', false)
        ->assertSee('ui-nav-purple', false)
        ->assertSee(__('ui.nav.tab_menu'))
        ->assertSee(__('ui.user_menu.logout'));
});

it('draws a money input as a decimal field with a currency suffix', function () {
    view()->share('errors', new ViewErrorBag);

    $html = Blade::render('<x-ui.money-input label="Tutar" wire:model="amount" suffix="TL" />');

    expect($html)->toContain('inputmode="decimal"')->toContain('placeholder="0,00"')->toContain('wire:model="amount"')->toContain('ui-input-suffix')->toContain('>TL<');
});

it('draws a date input that shows dd.mm.yyyy and binds with wire:model', function () {
    view()->share('errors', new ViewErrorBag);

    $html = Blade::render('<x-ui.date-input label="Tarih" wire:model="paid_on" />');

    expect($html)->toContain('gg.aa.yyyy')->toContain('x-modelable="value"')->toContain('wire:model="paid_on"')->toContain('Ocak')->toContain('Pz');
});

it('draws a textarea for an input of type textarea', function () {
    view()->share('errors', new ViewErrorBag);

    expect(Blade::render('<x-ui.input type="textarea" label="Not" wire:model="note" />'))->toContain('<textarea')->toContain('ui-textarea');
});

it('wraps an icon button in a tooltip pill', function () {
    expect(Blade::render('<x-ui.tooltip label="Kopyala"><button aria-label="Kopyala">x</button></x-ui.tooltip>'))
        ->toContain('data-tip="Kopyala"')->toContain('ui-tooltip');
});

it('makes a row with a link open it and end in a chevron, and a row without one plain', function () {
    expect(Blade::render('<x-ui.row href="/app/customers/1"><td>A</td></x-ui.row>'))
        ->toContain('data-href="/app/customers/1"')->toContain('ui-chevron');

    expect(Blade::render('<x-ui.row><td>A</td></x-ui.row>'))->not->toContain('data-href')->not->toContain('ui-chevron');
});

it('draws a chip pressed only while its filter is on', function () {
    expect(Blade::render('<x-ui.chip :on="true">Açık</x-ui.chip>'))->toContain('aria-pressed="true"')->toContain('is-on');
    expect(Blade::render('<x-ui.chip>Kapalı</x-ui.chip>'))->toContain('aria-pressed="false"')->not->toContain('is-on');
});

it('draws a table with sortable heads, the sorted one marked, and hides columns by breakpoint', function () {
    $columns = [
        ['key' => 'name', 'label' => 'Ad', 'sortable' => true],
        ['key' => 'date', 'label' => 'Tarih', 'from' => 'md'],
        ['key' => 'count', 'label' => 'Adet', 'from' => 'lg', 'end' => true],
    ];

    $html = Blade::render('<x-ui.table :columns="$columns" sort="name" direction="desc"><tr><td>x</td></tr></x-ui.table>', ['columns' => $columns]);

    expect($html)
        ->toContain("wire:click=\"sortBy('name')\"")
        ->toContain('aria-sort="descending"')
        ->toContain('ui-from-md')
        ->toContain('ui-from-lg is-end')
        ->toContain('<table class="ui-table">');
});

it('shows the empty state instead of the table when the table is empty', function () {
    $html = Blade::render('<x-ui.table :columns="[]" :empty="true"><x-slot:emptyState>Boş</x-slot:emptyState></x-ui.table>');

    expect($html)->toContain('Boş')->not->toContain('<table');
});
