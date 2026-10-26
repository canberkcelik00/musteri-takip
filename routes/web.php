<?php

use App\Http\Controllers\App\LogoutController;
use App\Http\Controllers\App\QuotePdfController;
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
use App\Livewire\Pages\Settings\Servers as SettingsServers;
use App\Livewire\Pages\Today;
use Illuminate\Support\Facades\Route;

// The glass interface (Faz 10): served from the site root, route names `app.*`.
Route::name('app.')->group(function (): void {
    Route::livewire('/login', Login::class)->middleware('guest')->name('login');

    Route::middleware(['auth', 'active'])->group(function (): void {
        Route::livewire('/', Today::class)->name('today');
        Route::livewire('/profile', Profile::class)->name('profile');
        Route::livewire('/customers', CustomersIndex::class)->name('customers.index');
        Route::livewire('/customers/{customer}', CustomersShow::class)->name('customers.show');
        Route::livewire('/charges', ChargesIndex::class)->name('charges.index');
        Route::livewire('/payments', PaymentsIndex::class)->name('payments.index');
        Route::livewire('/quotes', QuotesIndex::class)->name('quotes.index');
        Route::get('/quotes/{quote}/pdf', QuotePdfController::class)->name('quotes.pdf');
        Route::livewire('/catalog/services', CatalogServices::class)->name('catalog.services');
        Route::livewire('/catalog/packages', CatalogPackages::class)->name('catalog.packages');
        Route::livewire('/settings', SettingsGeneral::class)->name('settings');
        Route::livewire('/settings/payment-methods', SettingsPaymentMethods::class)->name('payment-methods');
        Route::livewire('/settings/servers', SettingsServers::class)->name('servers');
        Route::post('/logout', LogoutController::class)->name('logout');
    });
});
