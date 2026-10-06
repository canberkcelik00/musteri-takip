<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Actions\DeletePaymentAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Customer;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer card's "Son ödemeler" (T-66): the customer's 5 most recent payments. The amount
 * shows the domains of the services behind the charges it paid down ({@see Payment::domains()})
 * as a faded line; a payment with no allocations, or allocated only to charges without a
 * service, has none. The row action deletes one; it only calls the Actions in
 * app/Actions/Payments. Recording a payment is the card's page header action
 * (RecordPaymentAction), not a header action here (T-63). "Tüm ödemeler" links to the
 * customer's full history in the menu's Ödemeler list ({@see PaymentResource}).
 * Responsive (T-68, no horizontal scroll at any width): date and amount (+ domains) always
 * show; payment method from `md`, note from `lg`. Below `md` the row action button (delete) is
 * hidden and the amount column opens the same confirmation instead — there is no "edit" for a
 * payment (T-42), so deleting is the only, already-confirmed option either way. The domains line
 * may break anywhere below `xl` (`fi-wrap-description`, G-4B.7) so a long one never widens the
 * card at 1024 px.
 */
class PaymentsRelationManager extends RelationManager
{
    use RefreshesOnPaymentOrChargeChange;

    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.recent_heading');
    }

    /**
     * Filament makes relation managers read-only on view pages; the delete action lives on the customer card.
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['paymentMethod', 'allocations.charge.customerService.items'])
                ->orderByDesc('paid_on')
                ->orderByDesc('id')
                ->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('paid_on')
                    ->label(__('payments.paid_on'))
                    ->date(),
                MoneyColumn::make('amount')
                    ->label(__('payments.amount'))
                    ->extraAttributes(['class' => 'fi-wrap-description'])
                    ->description(fn (Payment $record): ?string => filled($domains = $record->domains())
                        ? implode(', ', $domains)
                        : null)
                    ->action(DeletePaymentAction::make('deletePaymentMobile')),
                TextColumn::make('paymentMethod.name')
                    ->label(__('payments.payment_method'))
                    ->visibleFrom('md'),
                TextColumn::make('note')
                    ->label(__('payments.note'))
                    ->placeholder(__('money.empty'))
                    ->wrap()
                    ->visibleFrom('lg'),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label(__('payments.view_all'))
                    ->link()
                    ->url(fn (): string => PaymentResource::getUrl('index', [
                        'filters' => ['customer' => ['value' => $customer->getKey()]],
                    ])),
            ])
            ->recordActions([
                DeletePaymentAction::make()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ])
            ->emptyStateHeading(__('payments.recent_empty_heading'))
            ->emptyStateDescription(__('payments.recent_empty_description'))
            ->emptyStateActions([
                RecordPaymentAction::make('recordPaymentEmpty')->customer($customer)->color('gray'),
            ]);
    }
}
