<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\ChargeStatus;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Support\ChargeStatusBadge;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Providers\AppServiceProvider;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * The customer card's "Açık borçlar" (T-66): only the customer's open charges, oldest due date
 * first. The remaining amount and the overdue / partially paid state are derived from the
 * allocations, not stored; a partially paid charge shows the original amount as a faded "/
 * total" line under the remaining amount. The description shows the linked service's domains
 * (if any, {@see CustomerService::domains()}) as a faded line; a manual charge
 * without a service has none. "Borç ekle" and the row actions ("Ödeme al", "Düzenle"; split and
 * cancel live in the edit panel's "Borç işlemleri" section, G-4.17) only call the Actions in
 * app/Actions; the rules live there. "Tüm borçlar" links to the customer's
 * full, unfiltered history in the menu's Borçlar list ({@see ChargeResource}).
 * Responsive (T-68/T-70/G-4.17/G-4.18, no horizontal scroll at any width, classic table columns
 * kept intact at every width — no {@see Stack}/`Split`, see {@see ChargeStatusBadge}):
 * description (+ domains) and remaining always show, with the state badge under "Kalan" (only for
 * overdue, partially paid or due-soon — a plain open charge shows nothing) next to the "/ total"
 * line when applicable; there is no separate "Durum" column. The due date is a column from `xl`;
 * below `xl` it is a faded line under the description instead, so it shows exactly once at every
 * width and the table fits the card at 1024 px.
 */
class ChargesRelationManager extends RelationManager
{
    use RefreshesOnPaymentOrChargeChange;

    /**
     * Hides a row action's button below `md` (T-68); the row's description column opens the
     * same options via {@see self::mobileEditAction()} at that width instead.
     */
    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

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
                    ->description(fn (Charge $record): Htmlable => $this->descriptionBelow($record))
                    ->action($this->mobileEditAction()),
                TextColumn::make('due_date')
                    ->label(__('charges.due_date'))
                    ->date()
                    ->visibleFrom('xl'),
                MoneyColumn::make('remaining')
                    ->label(__('charges.remaining'))
                    ->state(fn (Charge $record): ?int => $record->status === ChargeStatus::Open ? $record->remainingAmount() : null)
                    ->currencyAttribute('currency')
                    ->description(fn (Charge $record): ?Htmlable => $this->remainingDescription($record)),
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
                RecordPaymentAction::make()
                    ->customer($customer)
                    ->icon(null)
                    ->color('gray')
                    ->visible(fn (Charge $record): bool => $record->status === ChargeStatus::Open),
                EditChargeAction::make()->icon(null)->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ])
            ->emptyStateHeading(__('charges.open_empty_heading'))
            ->emptyStateDescription(__('charges.open_empty_description'))
            ->emptyStateActions([
                CreateManualChargeAction::make('createManualChargeEmpty')->customer($customer)->color('gray'),
            ]);
    }

    /**
     * The description column's click target below `md`, where the "Düzenle" button is hidden
     * (T-68, G-4.17): the same edit panel, whose "Borç işlemleri" section keeps "Böl" and "İptal
     * et" reachable. "Ödeme al" stays a visible row button at every width, so taking a payment is
     * one tap on mobile too. Named distinctly from the row action ("…Mobile"): the table caches
     * actions by name regardless of whether they came from a column or a row, so reusing a name
     * here would let one silently overwrite the other (confirmed by reproducing it).
     */
    private function mobileEditAction(): Action
    {
        return EditChargeAction::make('editChargeMobile');
    }

    /**
     * The description column's faded lines: the linked service's domains (if any) and, below `xl`
     * where the "Vade" column is hidden, the formatted due date (`fi-due-below-xl`, theme.css). The
     * domains may break anywhere (`fi-charge-domains`) so a long one never widens the narrow card.
     */
    private function descriptionBelow(Charge $record): Htmlable
    {
        $domains = $record->customerService?->domains();

        $html = filled($domains)
            ? '<span class="fi-charge-domains">'.e(implode(', ', $domains)).'</span>'
            : '';
        $html .= '<span class="fi-due-below-xl">'
            .e(__('charges.due_date').': '.$record->due_date->format(AppServiceProvider::DATE_FORMAT))
            .'</span>';

        return new HtmlString($html);
    }

    /**
     * "Kalan"'s description: the "/ total" line for a partially paid charge, plus the state badge
     * ({@see ChargeStatusBadge}) at every width for an overdue, partially paid or due-soon charge
     * (a plain open one shows nothing; this table only ever lists open charges). G-4.17: this
     * replaced the desktop "Durum" column, whose width pushed "Ödeme al" and "Düzenle" out of the
     * card at 1280 px.
     */
    private function remainingDescription(Charge $record): ?Htmlable
    {
        $original = $record->isPartiallyPaid()
            ? '/ '.(new Money($record->amount, $record->currency))->format()
            : null;

        $badge = ChargeStatusBadge::html($record);

        if ($original === null && $badge === null) {
            return null;
        }

        $html = e($original ?? '');

        if ($badge !== null) {
            $html .= $badge->toHtml();
        }

        return new HtmlString($html);
    }
}
