<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\TextEntry;

/**
 * The two sections of the customer card's right-hand column (billing details, notes), T-66.
 * `ViewCustomer::content()` assembles them into the card's layout; the strip of open debt and
 * credit balance moved into the page header in G-4B.5 (`ViewCustomer::getHeader()`).
 */
class CustomerInfolist
{
    /**
     * @return list<TextEntry>
     */
    public static function billingSchema(): array
    {
        return [
            TextEntry::make('legal_title')
                ->label(__('customers.legal_title'))
                ->inlineLabel()
                ->placeholder(__('money.empty')),
            TextEntry::make('tax_office')
                ->label(__('customers.tax_office'))
                ->inlineLabel()
                ->placeholder(__('money.empty')),
            TextEntry::make('tax_number')
                ->label(__('customers.tax_number'))
                ->inlineLabel()
                ->placeholder(__('money.empty')),
            TextEntry::make('billing_address')
                ->label(__('customers.billing_address'))
                ->inlineLabel()
                ->placeholder(__('money.empty')),
        ];
    }

    /**
     * @return list<TextEntry>
     */
    public static function notesSchema(): array
    {
        return [
            TextEntry::make('notes')
                ->hiddenLabel()
                ->placeholder(__('money.empty')),
        ];
    }
}
