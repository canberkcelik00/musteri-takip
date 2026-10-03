<?php

namespace App\Filament\Resources\Charges\Tables;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\ChargeStatusBadge;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use App\Support\Money;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * The charge list (docs/UI.md §5; Faz 4B, G-4B.4): the state sits in its own "Durum" column from
 * `lg` up, as in the reference screen (T-80), and under "Kalan" below `lg`, where the column is
 * hidden; a total row per currency closes the list.
 */
class ChargesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('customer')
                ->withSum('allocations', 'amount'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('due_date')->orderBy('id'))
            ->columns([
                TextColumn::make('customer.short_name')
                    ->label(__('charges.customer'))
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->url(fn (Charge $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                TextColumn::make('description')
                    ->label(__('charges.description'))
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label(__('charges.due_date'))
                    ->date(),
                TextColumn::make('status')
                    ->label(__('charges.status'))
                    ->state(fn (Charge $record): string => ChargeStatusBadge::label($record))
                    ->badge(fn (Charge $record): bool => ChargeStatusBadge::color($record) !== null)
                    ->color(fn (Charge $record): ?string => ChargeStatusBadge::color($record))
                    ->wrap()
                    ->visibleFrom('lg'),
                MoneyColumn::make('remaining')
                    ->label(__('charges.remaining'))
                    ->state(fn (Charge $record): ?int => $record->status === ChargeStatus::Open ? $record->remainingAmount() : null)
                    ->currencyAttribute('currency')
                    ->description(fn (Charge $record): ?Htmlable => self::remainingDescription($record)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('charges.status'))
                    ->options(ChargeStatus::class)
                    ->default(ChargeStatus::Open->value),
                Filter::make('only_overdue')
                    ->label(__('charges.only_overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['overdue'])),
                Filter::make('due_this_month')
                    ->label(__('charges.due_this_month'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['dueThisMonth'])),
                Filter::make('due_soon')
                    ->label(__('charges.due_soon_filter'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['dueSoon'])),
                SelectFilter::make('currency')
                    ->label(__('charges.currency'))
                    ->options(Currency::class),
                SelectFilter::make('customer')
                    ->label(__('charges.customer'))
                    ->relationship('customer', 'short_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                RecordPaymentAction::make()
                    ->icon(null)
                    ->color('gray')
                    ->visible(fn (Charge $record): bool => $record->status === ChargeStatus::Open),
                EditChargeAction::make()->icon(null),
            ])
            ->contentFooter(fn (HasTable $livewire): ?View => self::totalFooter($livewire))
            ->emptyStateHeading(__('charges.empty_heading'))
            ->emptyStateDescription(__('charges.empty_description'))
            ->emptyStateActions([
                CreateManualChargeAction::make()->color('gray'),
            ]);
    }

    /**
     * "4.000,00 TL'den" under "Kalan" for a partially paid charge, and, below `lg` only, the state
     * badge that the hidden "Durum" column would have shown.
     */
    private static function remainingDescription(Charge $record): ?Htmlable
    {
        $original = $record->isPartiallyPaid()
            ? __('charges.original_amount.'.$record->currency->value, ['amount' => (new Money($record->amount, $record->currency))->format()])
            : null;

        $badge = ChargeStatusBadge::mobileHtml($record);

        if ($original === null && $badge === null) {
            return null;
        }

        return new HtmlString(($original === null ? '' : '<span class="fi-original-amount">'.e($original).'</span>').($badge?->toHtml() ?? ''));
    }

    /**
     * Remaining open debt of the filtered, searched list, one total per currency: the same open
     * charges and remaining amounts the "Kalan" column shows, added with the per-currency helper.
     * Nothing to show (a list of paid or cancelled charges) gives no footer.
     */
    private static function totalFooter(HasTable $livewire): ?View
    {
        $charges = $livewire->getFilteredTableQuery()?->get();

        $totals = Money::sumByCurrency(
            ($charges ?? collect())
                ->filter(fn (Charge $charge): bool => $charge->status === ChargeStatus::Open && $charge->remainingAmount() > 0)
                ->map(fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency)),
        );

        if ($totals === []) {
            return null;
        }

        return view('filament.tables.charges-total-footer', [
            'totals' => array_map(fn (Money $money): string => $money->format(), array_values($totals)),
        ]);
    }
}
