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
                        : null),
                TextColumn::make('due_date')
                    ->label(__('charges.due_date'))
                    ->date()
                    ->visibleFrom('md'),
                MoneyColumn::make('remaining')
                    ->label(__('charges.remaining'))
                    ->state(fn (Charge $record): ?int => $record->status === ChargeStatus::Open ? $record->remainingAmount() : null)
                    ->currencyAttribute('currency')
                    ->description(fn (Charge $record): ?string => $record->isPartiallyPaid()
                        ? '/ '.(new Money($record->amount, $record->currency))->format()
                        : null),
                ChargeStatusColumn::make('status'),
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
                EditChargeAction::make(),
                ActionGroup::make([
                    SplitChargeAction::make(),
                    CancelChargeAction::make(),
                ]),
            ]);
    }
}
