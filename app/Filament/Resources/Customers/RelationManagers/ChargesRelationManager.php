<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\ChargeStatus;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\WhatsAppReminderAction;
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
 * (if any, {@see CustomerService::domains()}) as a faded line; an old manual charge
 * without a service has none. The row's "Ödeme al" only calls the Actions in
 * app/Actions; the rules live there. "Düzenle" is no longer a row button (G-4B.5, as in the
 * reference screen): clicking the description opens the same edit panel at every width, with
 * split and cancel in its "Borç işlemleri" section (G-4.17). "Tüm borçlar" links to the customer's
 * full, unfiltered history in the menu's Borçlar list ({@see ChargeResource}).
 * Responsive (T-68/T-70/G-4.17/G-4.18/G-4B.5, no horizontal scroll at any width, classic table
 * columns kept intact at every width — no {@see Stack}/`Split`, see {@see ChargeStatusBadge}):
 * description (+ domains) and remaining always show. The due date and the "Durum" column are
 * columns from `xl` (card-specific: the left column is narrow); below `xl` the date is a faded
 * line under the description and the state badge (only for overdue, partially paid or due-soon —
 * a plain open charge shows nothing) sits under "Kalan" next to the "/ total" line, so each shows
 * exactly once at every width and the table fits the card at 1024 px.
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
                ->with(['customer.primaryContact', 'customerService.items'])
                ->orderBy('due_date')
                ->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('description')
                    ->label(__('charges.description'))
                    ->wrap()
                    ->description(fn (Charge $record): Htmlable => $this->descriptionBelow($record))
                    ->action(EditChargeAction::make()),
                TextColumn::make('due_date')
                    ->label(__('charges.due_date'))
                    ->date()
                    ->visibleFrom('xl'),
                TextColumn::make('status')
                    ->label(__('charges.status'))
                    ->state(fn (Charge $record): ?string => ChargeStatusBadge::color($record) === null ? null : ChargeStatusBadge::label($record))
                    ->badge()
                    ->color(fn (Charge $record): ?string => ChargeStatusBadge::color($record))
                    ->wrap()
                    ->visibleFrom('xl'),
                MoneyColumn::make('remaining')
                    ->label(__('charges.remaining'))
                    ->state(fn (Charge $record): ?int => $record->status === ChargeStatus::Open ? $record->remainingAmount() : null)
                    ->currencyAttribute('currency')
                    ->description(fn (Charge $record): ?Htmlable => $this->remainingDescription($record)),
            ])
            ->headerActions([
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
                WhatsAppReminderAction::make(),
            ])
            ->emptyStateHeading(__('charges.open_empty_heading'))
            ->emptyStateDescription(__('charges.open_empty_description'));
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
        if ($record->reminder_sent_at !== null) {
            $html .= '<span class="fi-charge-reminder">'
                .e(__('reminders.sent_on', ['date' => $record->reminder_sent_at->format(AppServiceProvider::DATE_FORMAT)]))
                .'</span>';
        }

        $html .= '<span class="fi-due-below-xl">'
            .e(__('charges.due_date').': '.$record->due_date->format(AppServiceProvider::DATE_FORMAT))
            .'</span>';

        return new HtmlString($html);
    }

    /**
     * "Kalan"'s description: the original amount ("4.000 TL'den", G-4B.6) for a partially paid charge, plus — below `xl`
     * only, where the "Durum" column is hidden — the state badge ({@see ChargeStatusBadge}) for an
     * overdue, partially paid or due-soon charge (a plain open one shows nothing; this table only
     * ever lists open charges). G-4B.5: the column is back from `xl` (the row now holds just
     * "Ödeme al", so it fits; G-4.17 had removed it with "Düzenle" in the row).
     */
    private function remainingDescription(Charge $record): ?Htmlable
    {
        $original = $record->isPartiallyPaid()
            ? __('charges.original_amount.'.$record->currency->value, ['amount' => (new Money($record->amount, $record->currency))->format()])
            : null;

        $badge = ChargeStatusBadge::mobileHtml($record, 'fi-badge-below-xl');

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
