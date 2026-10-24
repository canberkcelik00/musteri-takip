<?php

use App\Http\Controllers\App\LogoutController;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\Profile;
use App\Livewire\Pages\Customers\Index as CustomersIndex;
use App\Livewire\Pages\Today;
use Illuminate\Support\Facades\Route;

// The glass interface (Faz 10). Loaded by bootstrap/app.php under the /app prefix with route names `app.*`.
Route::livewire('/login', Login::class)->middleware('guest')->name('login');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/', Today::class)->name('today');
    Route::livewire('/profile', Profile::class)->name('profile');
    Route::livewire('/customers', CustomersIndex::class)->name('customers.index');
    Route::post('/logout', LogoutController::class)->name('logout');
});
