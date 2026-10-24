<?php

namespace App\Support\Ui;

use App\Models\Customer;
use Illuminate\Support\Facades\Route;

/**
 * Addresses that exist both in the glass interface and in the Filament panel it replaces: the glass one wins as
 * soon as its route exists.
 */
final class Links
{
    public static function customer(Customer $customer): string
    {
        return Route::has('app.customers.show')
            ? route('app.customers.show', $customer)
            : route('filament.panel.resources.customers.view', ['record' => $customer]);
    }
}
