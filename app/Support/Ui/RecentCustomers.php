<?php

namespace App\Support\Ui;

use App\Models\Customer;

/**
 * The customers the signed-in user looked at last, newest first, kept in the session only (docs/UI.md §3): nothing
 * is stored in the database, and signing out starts the list over.
 */
final class RecentCustomers
{
    public const int LIMIT = 5;

    private const string SESSION_KEY = 'recent_customers';

    public static function remember(Customer $customer): void
    {
        $ids = array_values(array_unique([$customer->id, ...self::ids()]));

        session()->put(self::SESSION_KEY, array_slice($ids, 0, self::LIMIT));
    }

    /**
     * @return list<int>
     */
    public static function ids(): array
    {
        /** @var list<int> $ids */
        $ids = session()->get(self::SESSION_KEY, []);

        return $ids;
    }
}
