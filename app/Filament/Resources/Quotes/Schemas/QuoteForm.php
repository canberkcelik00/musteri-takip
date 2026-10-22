<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Enums\QuoteStatus;
use App\Filament\Actions\AcceptQuoteAction;
use App\Filament\Actions\DeleteQuoteAction;
use App\Filament\Actions\DownloadQuotePdfAction;
use App\Filament\Actions\RejectQuoteAction;
use App\Filament\Actions\SendQuoteAction;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\QuotableItems;
use App\Filament\Support\RecordActionsSection;
use App\Models\Customer;
use App\Models\Quote;
use App\Support\Money;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The fields of the quote windows (docs/UI.md §5 Teklifler): who the quote is for — an existing
 * customer, or a person written by hand — and, when a draft is opened, its items and notes. A
 * quote that was sent opens with the same fields locked, and its rarer actions below.
 */
final class QuoteForm
{
    /**
     * The recipient: a customer, or the hand-written name, company, phone and e-mail, which show
     * only while no customer is chosen.
     *
     * @return list<Select|TextInput>
     */
    public static function recipientFields(): array
    {
        $noCustomer = fn (Get $get): bool => blank($get('customer_id'));

        return [
            Select::make('customer_id')
                ->label(__('quotes.customer'))
                ->helperText(__('quotes.customer_helper'))
                ->options(fn (): array => Customer::query()->notArchived()->orderBy('short_name')->pluck('short_name', 'id')->all())
                ->searchable()
                ->live()
                ->columnSpanFull(),
            TextInput::make('recipient_name')
                ->label(__('quotes.recipient_name'))
                ->required($noCustomer)
                ->visible($noCustomer)
                ->maxLength(255),
            TextInput::make('recipient_company')
                ->label(__('quotes.recipient_company'))
                ->visible($noCustomer)
                ->maxLength(255),
            TextInput::make('recipient_email')
                ->label(__('quotes.recipient_email'))
                ->email()
                ->required($noCustomer)
                ->visible($noCustomer)
                ->maxLength(255),
            TextInput::make('recipient_phone')
                ->label(__('quotes.recipient_phone'))
                ->tel()
                ->visible($noCustomer)
                ->maxLength(255),
        ];
    }

    public static function notes(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('quotes.notes'))
            ->columnSpanFull();
    }

    /**
     * The window of one quote: recipient, items and notes, then its rarer actions. Fields are
     * locked by the action once the quote is not a draft.
     *
     * @return list<Section>
     */
    public static function components(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->disabled(fn (Section $component): bool => ($record = $component->getRecord()) instanceof Quote && $record->status !== QuoteStatus::Draft)
                ->schema([
                    ...self::recipientFields(),
                    self::items(),
                    self::notes(),
                ]),
            RecordActionsSection::make(
                __('quotes.actions_section'),
                [
                    DownloadQuotePdfAction::make(),
                    SendQuoteAction::make('sendQuoteFromWindow'),
                    AcceptQuoteAction::make('acceptQuoteFromWindow'),
                    RejectQuoteAction::make('rejectQuoteFromWindow'),
                ],
                [DeleteQuoteAction::make('deleteQuoteFromWindow')],
            ),
        ];
    }

    /**
     * The lines of the quote: an existing one keeps its copied name and only its price, currency
     * and free flag change; a new one is picked from the catalog (not archived) and takes the
     * catalog's price and currency, which can then be changed.
     */
    private static function items(): Repeater
    {
        return Repeater::make('items')
            ->label(__('quotes.items'))
            ->addActionLabel(__('quotes.add_item'))
            ->reorderable()
            ->minItems(1)
            ->columns(2)
            ->columnSpanFull()
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
            ->schema([
                Hidden::make('id'),
                TextInput::make('name')
                    ->label(__('quotes.item_name'))
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => filled($get('id')))
                    ->columnSpanFull(),
                Select::make('item')
                    ->label(__('quotes.item'))
                    ->options(fn (): array => QuotableItems::options())
                    ->searchable()
                    ->required(fn (Get $get): bool => blank($get('id')))
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $quoted = QuotableItems::resolve($state);

                        if ($quoted === null) {
                            return;
                        }

                        $set('price', Money::formatAmount($quoted->price));
                        $set('currency', $quoted->currency->value);
                    })
                    ->visible(fn (Get $get): bool => blank($get('id')))
                    ->columnSpanFull(),
                MoneyInput::make('price')
                    ->label(__('quotes.price'))
                    ->required(fn (Get $get): bool => ! $get('is_free'))
                    ->currencyField(),
                CurrencySelect::make('currency'),
                Toggle::make('is_free')
                    ->label(__('quotes.is_free'))
                    ->live()
                    ->inline(false),
            ]);
    }
}
