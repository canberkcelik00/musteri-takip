<?php

use App\Livewire\Windows\CreateCustomer;
use App\Livewire\Windows\RecordPayment;

return [

    /*
    | Windows the WindowHost can open (name => Livewire component class). A window is opened by the
    | `open-window` event: `$dispatch('open-window', { name: 'record-payment', params: { ... } })`.
    | An unknown name opens nothing.
    */
    'windows' => [
        'record-payment' => RecordPayment::class,
        'create-customer' => CreateCustomer::class,
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
