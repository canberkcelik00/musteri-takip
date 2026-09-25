<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Infolists\Components\MoneyEntry;
use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The top of the customer card: customer details, open debt and credit balance,
 * the last two per currency and never summed across currencies.
 */
class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('customers.customer_section'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('short_name')
                            ->label(__('customers.short_name')),
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
                            ->placeholder(__('money.empty'))
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label(__('customers.notes'))
                            ->placeholder(__('money.empty'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('customers.balance_section'))
                    ->columns(2)
                    ->schema([
                        MoneyEntry::make('open_debt')
                            ->label(__('customers.open_debt'))
                            ->state(fn (Customer $record): array => $record->openChargeTotals()),
                        MoneyEntry::make('credit_balance')
                            ->label(__('customers.credit_balance'))
                            ->state(fn (Customer $record): array => $record->creditBalances()),
                    ]),
            ]);
    }
}
