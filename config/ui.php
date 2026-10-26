<?php

use App\Livewire\Windows\AcceptQuote;
use App\Livewire\Windows\AssignService;
use App\Livewire\Windows\CancelCharge;
use App\Livewire\Windows\CancelService;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\Contact;
use App\Livewire\Windows\CreateCustomer;
use App\Livewire\Windows\CreateQuote;
use App\Livewire\Windows\EditCatalogService;
use App\Livewire\Windows\EditCharge;
use App\Livewire\Windows\EditCustomer;
use App\Livewire\Windows\EditPackage;
use App\Livewire\Windows\EditPaymentMethod;
use App\Livewire\Windows\EditRole;
use App\Livewire\Windows\EditServer;
use App\Livewire\Windows\EditService;
use App\Livewire\Windows\EditTask;
use App\Livewire\Windows\EditUser;
use App\Livewire\Windows\InviteUser;
use App\Livewire\Windows\MarkFree;
use App\Livewire\Windows\OpenQuote;
use App\Livewire\Windows\PullFromServer;
use App\Livewire\Windows\ReactivateService;
use App\Livewire\Windows\RecordPayment;
use App\Livewire\Windows\RemoveFree;
use App\Livewire\Windows\SendQuote;
use App\Livewire\Windows\SendReminder;
use App\Livewire\Windows\SplitCharge;
use App\Livewire\Windows\ViewPayment;
use App\Livewire\Windows\ViewServerUsage;
use App\Livewire\Windows\ViewUsage;

return [

    /*
    | The permission each window needs (window name => `Permission` enum value), checked by the WindowHost before
    | it opens a window and by the window itself on every request (AuthorizesPermission). `confirm` decides per action
    | (see Confirmations::permission); `view-usage` shows no money, so it needs only the services permission.
    */
    'window_permissions' => [
        'record-payment' => 'money.manage',
        'edit-charge' => 'money.manage',
        'split-charge' => 'money.manage',
        'cancel-charge' => 'money.manage',
        'send-reminder' => 'money.manage',
        'view-payment' => 'money.view',
        'create-customer' => 'customers.manage',
        'edit-customer' => 'customers.manage',
        'contact' => 'customers.manage',
        'assign-service' => 'services.manage',
        'edit-service' => 'services.manage',
        'mark-free' => 'services.manage',
        'remove-free' => 'services.manage',
        'cancel-service' => 'services.manage',
        'reactivate-service' => 'services.manage',
        'view-usage' => 'services.view',
        'quote' => 'quotes.manage',
        'create-quote' => 'quotes.manage',
        'send-quote' => 'quotes.manage',
        'accept-quote' => 'quotes.manage',
        'edit-catalog-service' => 'catalog.manage',
        'edit-package' => 'catalog.manage',
        'edit-payment-method' => 'settings.manage',
        'edit-server' => 'settings.manage',
        'pull-from-server' => 'settings.manage',
        'view-server-usage' => 'settings.manage',
        'invite-user' => 'users.manage',
        'edit-user' => 'users.manage',
        'edit-role' => 'users.manage',
        'edit-task' => 'tasks.manage',
    ],

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
        'edit-catalog-service' => EditCatalogService::class,
        'edit-package' => EditPackage::class,
        'edit-payment-method' => EditPaymentMethod::class,
        'edit-server' => EditServer::class,
        'pull-from-server' => PullFromServer::class,
        'view-server-usage' => ViewServerUsage::class,
        'invite-user' => InviteUser::class,
        'edit-user' => EditUser::class,
        'edit-role' => EditRole::class,
        'edit-task' => EditTask::class,
        'confirm' => Confirm::class,
    ],

    /*
    | The sidebar. An item points at its `app.*` route and may name the `permission` the user needs to see it.
    | `color` is a menu square color
    | (`--mt-nav-*`), `icon` a heroicon name (outline set), `tab` (a label) puts the item in the bottom tab bar
    | of narrow screens.
    */
    'navigation' => [
        ['label' => 'ui.nav.today', 'icon' => 'calendar-days', 'color' => 'blue', 'route' => 'app.today', 'permission' => 'money.view', 'tab' => 'ui.nav.tab_today'],
        ['label' => 'ui.nav.customers', 'icon' => 'users', 'color' => 'indigo', 'route' => 'app.customers.index', 'tab' => 'ui.nav.tab_customers'],
        ['label' => 'ui.nav.tasks', 'icon' => 'clipboard-document-check', 'color' => 'pink', 'route' => 'app.tasks.index', 'permission' => 'tasks.manage', 'tab' => 'ui.nav.tab_tasks'],
        ['label' => 'ui.nav.quotes', 'icon' => 'document-text', 'color' => 'orange', 'route' => 'app.quotes.index', 'permission' => 'money.view'],
        [
            'group' => 'ui.nav.collections',
            'items' => [
                ['label' => 'ui.nav.open_charges', 'icon' => 'receipt-percent', 'color' => 'purple', 'route' => 'app.charges.index', 'permission' => 'money.view', 'tab' => 'ui.nav.tab_charges'],
                ['label' => 'ui.nav.payments', 'icon' => 'banknotes', 'color' => 'green', 'route' => 'app.payments.index', 'permission' => 'money.view'],
            ],
        ],
        [
            'group' => 'ui.nav.catalog',
            'collapsed' => true,
            'items' => [
                ['label' => 'ui.nav.services', 'icon' => 'squares-2x2', 'color' => 'teal', 'route' => 'app.catalog.services', 'permission' => 'catalog.manage'],
                ['label' => 'ui.nav.packages', 'icon' => 'cube', 'color' => 'teal', 'route' => 'app.catalog.packages', 'permission' => 'catalog.manage'],
            ],
        ],
        [
            'group' => 'ui.nav.settings',
            'items' => [
                ['label' => 'ui.nav.general', 'icon' => 'cog-6-tooth', 'color' => 'gray', 'route' => 'app.settings', 'permission' => 'settings.manage'],
                ['label' => 'ui.nav.payment_methods', 'icon' => 'credit-card', 'color' => 'gray', 'route' => 'app.payment-methods', 'permission' => 'settings.manage'],
                ['label' => 'ui.nav.servers', 'icon' => 'server', 'color' => 'gray', 'route' => 'app.servers', 'permission' => 'settings.manage'],
                ['label' => 'ui.nav.users', 'icon' => 'user-group', 'color' => 'gray', 'route' => 'app.users', 'permission' => 'users.manage'],
                ['label' => 'ui.nav.roles', 'icon' => 'shield-check', 'color' => 'gray', 'route' => 'app.roles', 'permission' => 'users.manage'],
            ],
        ],
    ],

];
