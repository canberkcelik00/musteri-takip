<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::customerSection(),
            ]);
    }

    public static function customerSection(): Section
    {
        return Section::make(__('customers.customer_section'))
            ->columns(2)
            ->schema([
                TextInput::make('short_name')
                    ->label(__('customers.short_name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                TextInput::make('legal_title')
                    ->label(__('customers.legal_title'))
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('tax_office')
                    ->label(__('customers.tax_office'))
                    ->maxLength(255),
                TextInput::make('tax_number')
                    ->label(__('customers.tax_number'))
                    ->maxLength(255),
                Textarea::make('billing_address')
                    ->label(__('customers.billing_address'))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('customers.notes'))
                    ->columnSpanFull(),
            ]);
    }

    public static function firstContactSection(): Section
    {
        return Section::make(__('customers.first_contact_section'))
            ->description(__('customers.first_contact_helper'))
            ->statePath('contact')
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label(__('contacts.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label(__('contacts.title'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('contacts.phone'))
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('contacts.email'))
                    ->email()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
