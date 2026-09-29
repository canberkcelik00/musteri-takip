<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\ChargeStatus;
use App\Filament\Actions\CancelChargeAction;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\SplitChargeAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Tables\Columns\ChargeStatusColumn;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer card's "Açık borçlar" (T-66): only the customer's open charges, oldest due date
 * first. The remaining amount and the overdue / partially paid state are derived from the
 * allocations, not stored; a partially paid charge shows the original amount as a faded "/
 * total" line under the remaining amount. The description shows the linked service's domains
 * (if any, {@see CustomerService::domains()}) as a faded line; a manual charge
 * without a service has none. "Borç ekle" and the row actions (edit, split, cancel) only call the
 * Actions in app/Actions/Charges; the rules live there. "Tüm borçlar" links to the customer's
 * full, unfiltered history in the menu's Borçlar list ({@see ChargeResource}).
 * Responsive (T-68, no horizontal scroll at any width): description (+ domains) and
 * remaining/status always show; due date from `md`.
 */
class ChargesRelationManager extends RelationManager
{
    use RefreshesOnPaymentOrChargeChange;

    /**
     * Hides a row action's button below `md` (T-68); the row's description column opens the
     * same options via {@see self::mobileEditAction()} at that width instead.
     */
    private const string HIDDEN_BELOW_MD = 'hidden md:inline-flex';

    protected static string $relationship = 'charges';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('charges.open_heading');
    }

    /**
     * Filament makes relation managers read-only on view pages; the charge actions live on the customer card.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $customer = $this->getOwnerRecord();
        assert($customer instanceof Customer);

        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', ChargeStatus::Open)
                ->withSum('allocations', 'amount')
                ->with('customerService.items')
                ->orderBy('due_date')
                ->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('description')
                    ->label(__('charges.description'))
                    ->wrap()
                    ->description(fn (Charge $record): ?string => filled($domains = $record->customerService?->domains())
                        ? implode(', ', $domains)
                        : null)
                    ->action($this->mobileEditAction()),
                TextColumn::make('due_date')
                    ->label(__('charges.due_date'))
                    ->date()
                    ->visibleFrom('md'),
                Stack::make([
                    MoneyColumn::make('remaining')
                        ->label(__('charges.remaining'))
                        ->state(fn (Charge $record): ?int => $record->status === ChargeStatus::Open ? $record->remainingAmount() : null)
                        ->currencyAttribute('currency')
                        ->description(fn (Charge $record): ?string => $record->isPartiallyPaid()
                            ? '/ '.(new Money($record->amount, $record->currency))->format()
                            : null),
                    TextColumn::make('statusBadge')
                        ->label(__('charges.status'))
                        ->hiddenFrom('md')
                        ->visible(fn (?Charge $record): bool => $record === null || $record->isOverdue() || $record->isPartiallyPaid() || $record->isDueSoon())
                        ->badge()
                        ->state(fn (Charge $record): string => match (true) {
                            $record->isOverdue() => __('charges.overdue_days', ['count' => $record->daysOverdue()]),
                            $record->isPartiallyPaid() => __('charges.partially_paid'),
                            default => __('charges.due_soon'),
                        })
                        ->color(fn (Charge $record): string => $record->isOverdue() ? 'danger' : 'warning'),
                ]),
                ChargeStatusColumn::make('status')
                    ->visibleFrom('md'),
            ])
            ->headerActions([
                CreateManualChargeAction::make()->customer($customer)->color('gray'),
                Action::make('viewAll')
                    ->label(__('charges.view_all'))
                    ->link()
                    ->url(fn (): string => ChargeResource::getUrl('index', [
                        'filters' => [
                            'customer' => ['value' => $customer->getKey()],
                            'status' => ['value' => ''],
                        ],
                    ])),
            ])
            ->recordActions([
                EditChargeAction::make()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
                ActionGroup::make([
                    SplitChargeAction::make(),
                    CancelChargeAction::make(),
                ])->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ]);
    }

    /**
     * The description column's click target below `md`, where the row actions are hidden
     * (T-68): "Düzenle", with "Böl" and "İptal et" added to its footer so they stay reachable.
     * `cancelParentActions()` closes this modal instead of also submitting the edit form. At
     * `md` and up the layout is unchanged: these stay separate, visible row actions
     * ({@see self::table()}). Named distinctly from the row actions ("…Mobile"): the table
     * caches actions by name regardless of whether they came from a column or a row, so reusing
     * a name here would let one silently overwrite the other (confirmed by reproducing it).
     */
    private function mobileEditAction(): Action
    {
        return EditChargeAction::make('editChargeMobile')
            ->extraModalFooterActions([
                SplitChargeAction::make('splitChargeMobile')->cancelParentActions(),
                CancelChargeAction::make('cancelChargeMobile')->cancelParentActions(),
            ]);
    }
}
