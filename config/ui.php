<?php

use App\Livewire\Windows\AcceptQuote;
use App\Livewire\Windows\AssignService;
use App\Livewire\Windows\CancelCharge;
use App\Livewire\Windows\CancelService;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\Contact;
use App\Livewire\Windows\CreateCustomer;
use App\Livewire\Windows\CreateQuote;
use App\Livewire\Windows\EditCharge;
use App\Livewire\Windows\EditCustomer;
use App\Livewire\Windows\EditService;
use App\Livewire\Windows\MarkFree;
use App\Livewire\Windows\OpenQuote;
use App\Livewire\Windows\ReactivateService;
use App\Livewire\Windows\RecordPayment;
use App\Livewire\Windows\RemoveFree;
use App\Livewire\Windows\SendQuote;
use App\Livewire\Windows\SendReminder;
use App\Livewire\Windows\SplitCharge;
use App\Livewire\Windows\ViewPayment;
use App\Livewire\Windows\ViewUsage;

return [

    /*
    | Windows the WindowHost can open (name => Livewire component class). A window is opened by the
    | `open-window` event: `$dispatch('open-window', { name: 'record-payment', params: { ... } })`.
    | An unknown name opens nothing.
    */
    'windows' => [
        'record-payment' => RecordPayment::class,
        'create-customer' => CreateCustomer::class,
        'edit-customer' => EditCustomer::class,
        'contact' => Contact::class,
        'assign-service' => AssignService::class,
        'edit-service' => EditService::class,
        'mark-free' => MarkFree::class,
        'remove-free' => RemoveFree::class,
        'cancel-service' => CancelService::class,
        'reactivate-service' => ReactivateService::class,
        'edit-charge' => EditCharge::class,
        'split-charge' => SplitCharge::class,
        'cancel-charge' => CancelCharge::class,
        'send-reminder' => SendReminder::class,
        'view-payment' => ViewPayment::class,
        'view-usage' => ViewUsage::class,
        'quote' => OpenQuote::class,
        'create-quote' => CreateQuote::class,
        'send-quote' => SendQuote::class,
        'accept-quote' => AcceptQuote::class,
        'confirm' => Confirm::class,
    ],

    /*
    | The sidebar. An item points at its `app.*` route once the screen is moved to the glass interface
    | (Faz 10) and until then at the `fallback` Filament route. `color` is a menu square color
    | (`--mt-nav-*`), `icon` a heroicon name (outline set), `tab` (a label) puts the item in the bottom tab bar
    | of narrow screens.
    */
    'navigation' => [
        ['label' => 'ui.nav.today', 'icon' => 'calendar-days', 'color' => 'blue', 'route' => 'app.today', 'fallback' => 'filament.panel.pages.dashboard', 'tab' => 'ui.nav.tab_today'],
        ['label' => 'ui.nav.customers', 'icon' => 'users', 'color' => 'indigo', 'route' => 'app.customers.index', 'fallback' => 'filament.panel.resources.customers.index', 'tab' => 'ui.nav.tab_customers'],
        ['label' => 'ui.nav.quotes', 'icon' => 'document-text', 'color' => 'orange', 'route' => 'app.quotes.index', 'fallback' => 'filament.panel.resources.quotes.index'],
        [
            'group' => 'ui.nav.collections',
            'items' => [
                ['label' => 'ui.nav.open_charges', 'icon' => 'receipt-percent', 'color' => 'purple', 'route' => 'app.charges.index', 'fallback' => 'filament.panel.resources.charges.index', 'tab' => 'ui.nav.tab_charges'],
                ['label' => 'ui.nav.payments', 'icon' => 'banknotes', 'color' => 'green', 'route' => 'app.payments.index', 'fallback' => 'filament.panel.resources.payments.index'],
            ],
        ],
        [
            'group' => 'ui.nav.catalog',
            'collapsed' => true,
            'items' => [
                ['label' => 'ui.nav.services', 'icon' => 'squares-2x2', 'color' => 'teal', 'route' => 'app.catalog.services', 'fallback' => 'filament.panel.resources.catalog-services.index'],
                ['label' => 'ui.nav.packages', 'icon' => 'cube', 'color' => 'teal', 'route' => 'app.catalog.packages', 'fallback' => 'filament.panel.resources.packages.index'],
            ],
        ],
        [
            'group' => 'ui.nav.settings',
            'items' => [
                ['label' => 'ui.nav.general', 'icon' => 'cog-6-tooth', 'color' => 'gray', 'route' => 'app.settings', 'fallback' => 'filament.panel.pages.settings'],
                ['label' => 'ui.nav.payment_methods', 'icon' => 'credit-card', 'color' => 'gray', 'route' => 'app.payment-methods', 'fallback' => 'filament.panel.resources.payment-methods.index'],
                ['label' => 'ui.nav.servers', 'icon' => 'server', 'color' => 'gray', 'route' => 'app.servers', 'fallback' => 'filament.panel.resources.servers.index'],
            ],
        ],
    ],

];
