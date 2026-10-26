<?php

use App\Http\Controllers\App\LogoutController;
use App\Http\Controllers\App\QuotePdfController;
use App\Livewire\Pages\Auth\AcceptInvitation;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\Profile;
use App\Livewire\Pages\Catalog\Packages as CatalogPackages;
use App\Livewire\Pages\Catalog\Services as CatalogServices;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Customers\Index as CustomersIndex;
use App\Livewire\Pages\Customers\Show as CustomersShow;
use App\Livewire\Pages\Payments\Index as PaymentsIndex;
use App\Livewire\Pages\Quotes\Index as QuotesIndex;
use App\Livewire\Pages\Settings\General as SettingsGeneral;
use App\Livewire\Pages\Settings\PaymentMethods as SettingsPaymentMethods;
use App\Livewire\Pages\Settings\Roles as SettingsRoles;
use App\Livewire\Pages\Settings\Servers as SettingsServers;
use App\Livewire\Pages\Settings\Users as SettingsUsers;
use App\Livewire\Pages\Tasks\Index as TasksIndex;
use App\Livewire\Pages\Today;
use Illuminate\Support\Facades\Route;

// The glass interface (Faz 10): served from the site root, route names `app.*`.
Route::name('app.')->group(function (): void {
    Route::livewire('/login', Login::class)->middleware('guest')->name('login');
    Route::livewire('/invitation/{user}', AcceptInvitation::class)->middleware(['guest', 'signed'])->name('invitation');

    Route::middleware(['auth', 'active'])->group(function (): void {
        Route::livewire('/', Today::class)->middleware('money-home')->name('today');
        Route::livewire('/profile', Profile::class)->name('profile');
        Route::livewire('/customers', CustomersIndex::class)->name('customers.index');
        Route::livewire('/customers/{customer}', CustomersShow::class)->name('customers.show');
        Route::livewire('/tasks', TasksIndex::class)->middleware('can:tasks.manage')->name('tasks.index');
        Route::livewire('/charges', ChargesIndex::class)->middleware('can:money.view')->name('charges.index');
        Route::livewire('/payments', PaymentsIndex::class)->middleware('can:money.view')->name('payments.index');
        Route::livewire('/quotes', QuotesIndex::class)->middleware('can:money.view')->name('quotes.index');
        Route::get('/quotes/{quote}/pdf', QuotePdfController::class)->middleware('can:money.view')->name('quotes.pdf');
        Route::livewire('/catalog/services', CatalogServices::class)->middleware('can:catalog.manage')->name('catalog.services');
        Route::livewire('/catalog/packages', CatalogPackages::class)->middleware('can:catalog.manage')->name('catalog.packages');
        Route::livewire('/settings', SettingsGeneral::class)->middleware('can:settings.manage')->name('settings');
        Route::livewire('/settings/payment-methods', SettingsPaymentMethods::class)->middleware('can:settings.manage')->name('payment-methods');
        Route::livewire('/settings/servers', SettingsServers::class)->middleware('can:settings.manage')->name('servers');
        Route::livewire('/settings/users', SettingsUsers::class)->middleware('can:users.manage')->name('users');
        Route::livewire('/settings/roles', SettingsRoles::class)->middleware('can:users.manage')->name('roles');
        Route::post('/logout', LogoutController::class)->name('logout');
    });
});
