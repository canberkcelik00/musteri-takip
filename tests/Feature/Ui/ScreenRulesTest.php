<?php

use App\Livewire\Pages\Catalog\Packages;
use App\Livewire\Pages\Catalog\Services;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Customers\Index as CustomersIndex;
use App\Livewire\Pages\Payments\Index as PaymentsIndex;
use App\Livewire\Pages\Settings\Servers as ServersPage;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

// UI.md compliance of the glass screens (G-10.13).

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('puts a text "Kapat" at the top right of every form window but not of the read-only ones', function () {
    $form = Blade::render('<x-ui.window title="Form" submit="save">x</x-ui.window>');
    $readOnly = Blade::render('<x-ui.window title="Ayrıntı">x</x-ui.window>');

    expect($form)->toContain('ui-window-header has-close')->toContain('ui-window-close')->toContain(__('actions.close'));
    expect($readOnly)->not->toContain('ui-window-close');
});

it('draws the filtered record count at the right end of the table toolbar', function () {
    $columns = [['key' => 'name', 'label' => 'Ad']];
    $html = Blade::render('<x-ui.table :columns="$columns" count="8 müşteri"><x-slot:toolbar>Ara</x-slot:toolbar><tr><td>x</td></tr></x-ui.table>', ['columns' => $columns]);

    expect($html)->toContain('<span class="ui-toolbar-count">8 müşteri</span>');
});

it('draws a removable filter chip with a named "×" button', function () {
    $html = Blade::render('<x-ui.filter-chip label="Durum: Açık" wire:click="clear" />');

    expect($html)->toContain('Durum: Açık')->toContain('ui-chip-x')->toContain('wire:click="clear"')
        ->toContain(__('ui.table.remove_filter').': Durum: Açık');
});

it('shows the default charge filter as a chip that clears the filter', function () {
    Charge::factory()->create();

    Livewire::test(ChargesIndex::class)
        ->assertSeeHtml('ui-chip-x')->assertSee('Durum: Açık')
        ->set('status', 'all')
        ->assertDontSeeHtml('ui-chip-x')->assertDontSee('Durum: Açık');
});

it('right-aligns the amount columns of the customer, payment and charge lists', function () {
    $customer = Customer::factory()->create();
    Payment::factory()->create(['customer_id' => $customer->id]);

    $head = fn (string $html, string $label): int|false => preg_match('/<th[^>]*\bis-end\b[^>]*>(?:\s|<!--.*?-->)*'.preg_quote($label, '/').'/us', $html);

    $customers = Livewire::test(CustomersIndex::class)->assertSee(__('customers.create'))->assertSeeHtml('<td class="is-end">');
    expect($head($customers->html(), __('customers.open_debt')))->toBe(1);

    $payments = Livewire::test(PaymentsIndex::class)->assertSeeHtml('<td class="is-end">');
    expect($head($payments->html(), __('payments.amount')))->toBe(1);
});

it('puts the record count in the toolbar of the lists, not under the title', function () {
    Customer::factory()->create();

    Livewire::test(CustomersIndex::class)
        ->assertSeeHtml('<span class="ui-toolbar-count">'.__('customers.count', ['count' => 1]).'</span>')
        ->assertDontSeeHtml('class="ui-subtitle"');
});

it('gives catalog and server rows "Düzenle" next to their one other action', function () {
    CatalogService::factory()->create();
    Package::factory()->create();
    Server::factory()->create();

    Livewire::test(Services::class)->assertSee(__('actions.edit'))->assertSee(__('quotes.create'));
    Livewire::test(Packages::class)->assertSee(__('actions.edit'))->assertSee(__('quotes.create'));
    Livewire::test(ServersPage::class)->assertSee(__('actions.edit'))->assertSee(__('servers.pull'));
});

it('puts text on the move and take-out buttons instead of arrows and a bin', function () {
    $views = File::allFiles(resource_path('views'));
    $offenders = [];

    foreach ($views as $file) {
        $content = $file->getContents();

        if (preg_match('/>\s*[↑↓→←]\s*<\/x-ui\.button>/u', $content) === 1 || preg_match('/<x-ui\.button[^>]*\bicon\b/', $content) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
    expect(__('actions.up'))->toBe('Yukarı')->and(__('actions.down'))->toBe('Aşağı')->and(__('actions.take_out'))->toBe('Çıkar');
});

it('gives the domain icons a tooltip instead of a title attribute', function () {
    $html = Blade::render('<x-ui.domains :domains="[\'logi.com.tr\']" />');

    expect($html)->toContain('ui-tooltip')->toContain('data-tip="'.__('customer_services.open_site').'"')
        ->toContain('data-tip="'.__('customer_services.copy_domain').'"')->not->toContain('title=');
});

it('has no inline heading, spacing or entity patches left in the views', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views/livewire')) as $file) {
        $content = $file->getContents();

        foreach (['text-[17px]', '!important', '&nbsp;', 'style="display: block"'] as $needle) {
            if (str_contains($content, $needle)) {
                $offenders[] = $file->getRelativePathname().' '.$needle;
            }
        }
    }

    expect($offenders)->toBe([]);
});
