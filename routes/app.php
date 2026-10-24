<?php

use App\Http\Controllers\App\LogoutController;
use App\Http\Controllers\App\QuotePdfController;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\Profile;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Customers\Index as CustomersIndex;
use App\Livewire\Pages\Customers\Show as CustomersShow;
use App\Livewire\Pages\Payments\Index as PaymentsIndex;
use App\Livewire\Pages\Quotes\Index as QuotesIndex;
use App\Livewire\Pages\Today;
use Illuminate\Support\Facades\Route;

// The glass interface (Faz 10). Loaded by bootstrap/app.php under the /app prefix with route names `app.*`.
Route::livewire('/login', Login::class)->middleware('guest')->name('login');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/', Today::class)->name('today');
    Route::livewire('/profile', Profile::class)->name('profile');
    Route::livewire('/customers', CustomersIndex::class)->name('customers.index');
    Route::livewire('/customers/{customer}', CustomersShow::class)->name('customers.show');
    Route::livewire('/charges', ChargesIndex::class)->name('charges.index');
    Route::livewire('/payments', PaymentsIndex::class)->name('payments.index');
    Route::livewire('/quotes', QuotesIndex::class)->name('quotes.index');
    Route::get('/quotes/{quote}/pdf', QuotePdfController::class)->name('quotes.pdf');
    Route::post('/logout', LogoutController::class)->name('logout');
});
