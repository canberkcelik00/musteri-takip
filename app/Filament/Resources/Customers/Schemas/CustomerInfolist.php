<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Infolists\Components\MoneyEntry;
use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

/**
 * The customer card's parts (T-66): a full-width strip of open debt and credit balance (per
 * currency, never summed across currencies), and the two sections of the right-hand column
 * (billing details, notes). `ViewCustomer::content()` assembles these into the card's layout.
 */
class CustomerInfolist
{
    /**
     * @return list<MoneyEntry>
     */
    public static function stripSchema(): array
    {
        return [
            MoneyEntry::make('open_debt')
                ->label(__('customers.open_debt'))
                ->size(TextSize::Large)
                ->weight(FontWeight::Bold)
                ->state(fn (Customer $record): array => $record->openChargeTotals()),
            MoneyEntry::make('credit_balance')
                ->label(__('customers.credit_balance'))
                ->size(TextSize::Large)
                ->weight(FontWeight::Bold)
                ->state(fn (Customer $record): array => $record->creditBalances()),
        ];
    }

    /**
     * @return list<TextEntry>
     */
    public static function billingSchema(): array
    {
        return [
            TextEntry::make('legal_title')
                ->label(__('customers.legal_title'))
                ->placeholder(__('money.empty')),
            TextEntry::make('tax_office')
                ->label(__('customers.tax_office'))
                ->placeholder(__('money.empty')),
            TextEntry::make('tax_number')
                ->label(__('customers.tax_number'))
                ->placeholder(__('money.empty')),
            TextEntry::make('billing_address')
                ->label(__('customers.billing_address'))
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
