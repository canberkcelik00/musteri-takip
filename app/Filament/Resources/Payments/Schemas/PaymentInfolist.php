<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\AllocationSource;
use App\Filament\Actions\DeletePaymentAction;
use App\Filament\Infolists\Components\MoneyEntry;
use App\Filament\Support\RecordActionsSection;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * The payment's detail window (G-4B.7, docs/UI.md §2 Kural 7, 8, 10): date, customer, amount, method,
 * note and, when part of the payment went to the credit balance, a line that says so; the allocation
 * rows sit in a collapsed section titled with their count, and "Sil" is the last thing in the window,
 * on a row of its own, red, outlined and confirmed. Shared by the payment list and the customer card.
 */
final class PaymentInfolist
{
    public const string DELETE_ACTION = 'deletePaymentFromDetail';

    /**
     * @return list<Section>
     */
    public static function components(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('paid_on')->label(__('payments.paid_on'))->date(),
                    TextEntry::make('customer.short_name')->label(__('payments.customer')),
                    MoneyEntry::make('amount')->label(__('payments.amount')),
                    TextEntry::make('paymentMethod.name')->label(__('payments.payment_method')),
                    TextEntry::make('note')
                        ->label(__('payments.note'))
                        ->placeholder(__('money.empty'))
                        ->columnSpanFull(),
                    TextEntry::make('credit_added')
                        ->label(__('enums.allocation_source.credit_balance'))
                        ->hiddenLabel()
                        ->state(fn (Payment $record): ?string => self::creditAddedText($record))
                        ->visible(fn (Payment $record): bool => self::creditAddedText($record) !== null)
                        ->columnSpanFull(),
                ]),
            Section::make(fn (Payment $record): string => __('payments.allocations_heading', ['count' => $record->allocations->count()]))
                ->collapsible()
                ->collapsed()
                ->hidden(fn (Payment $record): bool => $record->allocations->isEmpty())
                ->schema([
                    RepeatableEntry::make('allocations')
                        ->label(__('payments.allocations_label'))
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('charge.description')
                                ->label(__('payments.allocation_charge'))
                                ->placeholder(__('payments.allocation_fallback_description')),
                            TextEntry::make('amount')
                                ->label(__('payments.amount'))
                                ->state(fn (PaymentAllocation $record): string => (new Money($record->amount, $record->payment->currency))->format()),
                            TextEntry::make('source')
                                ->label(__('payments.allocation_source'))
                                ->formatStateUsing(fn (AllocationSource $state): string => $state->getLabel()),
                        ]),
                ]),
            RecordActionsSection::make(
                __('payments.actions_section'),
                [],
                [DeletePaymentAction::make(self::DELETE_ACTION)],
            ),
        ];
    }

    /**
     * "600 TL alacak bakiyesine eklendi": the part of the payment that was not allocated to any
     * charge when it was recorded; null when all of it was.
     */
    public static function creditAddedText(Payment $payment): ?string
    {
        $allocated = (int) $payment->allocations()->where('source', AllocationSource::Payment)->sum('amount');
        $credit = $payment->amount - $allocated;

        return $credit > 0
            ? __('payments.result_credit', ['amount' => (new Money($credit, $payment->currency))->format()])
            : null;
    }
}
