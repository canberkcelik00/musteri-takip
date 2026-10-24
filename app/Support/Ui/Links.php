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

    /**
     * The charge list with every status, limited to one customer (the card's "Tüm borçlar").
     */
    public static function customerCharges(Customer $customer): string
    {
        return Route::has('app.charges.index')
            ? route('app.charges.index', ['customer' => $customer->id, 'status' => 'all'])
            : route('filament.panel.resources.charges.index', ['filters' => ['customer' => ['value' => $customer->id], 'status' => ['value' => '']]]);
    }

    /**
     * The payment list limited to one customer (the card's "Tüm ödemeler").
     */
    public static function customerPayments(Customer $customer): string
    {
        return Route::has('app.payments.index')
            ? route('app.payments.index', ['customer' => $customer->id])
            : route('filament.panel.resources.payments.index', ['filters' => ['customer' => ['value' => $customer->id]]]);
    }

    public static function servers(): string
    {
        return route(Route::has('app.servers') ? 'app.servers' : 'filament.panel.resources.servers.index');
    }

    public static function quotes(): string
    {
        return route(Route::has('app.quotes.index') ? 'app.quotes.index' : 'filament.panel.resources.quotes.index');
    }
}
