<?php

namespace App\Support\Ui;

use App\Models\Customer;

/**
 * Addresses of the glass interface that several screens, windows and e-mails link to.
 */
final class Links
{
    public static function customer(Customer $customer): string
    {
        return route('app.customers.show', $customer);
    }

    /**
     * The charge list with every status, limited to one customer (the card's "Tüm borçlar").
     */
    public static function customerCharges(Customer $customer): string
    {
        return route('app.charges.index', ['customer' => $customer->id, 'status' => 'all']);
    }

    /**
     * Everyone's tasks of one customer, done ones included (the card's "Tümü ve bitenler").
     */
    public static function customerTasks(Customer $customer): string
    {
        return route('app.tasks.index', ['customer' => $customer->id, 'who' => 'all', 'status' => 'all']);
    }

    /**
     * The payment list limited to one customer (the card's "Tüm ödemeler").
     */
    public static function customerPayments(Customer $customer): string
    {
        return route('app.payments.index', ['customer' => $customer->id]);
    }

    public static function servers(): string
    {
        return route('app.servers');
    }

    public static function quotes(): string
    {
        return route('app.quotes.index');
    }
}
