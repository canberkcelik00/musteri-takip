<?php

namespace App\Filament\Support;

use App\Enums\ChargeErrorReason;
use App\Enums\CustomerServiceErrorReason;
use App\Enums\QuoteErrorReason;
use App\Exceptions\InvalidCharge;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Shows a business-rule refusal from an Action under the form field it is about (docs/UI.md
 * §2 Kural 4). The domain exceptions only carry a neutral reason code; which form field a
 * reason belongs to is a UI concern and lives here, not in the exception. A reason with no
 * field in the open form (or none at all) stays a notification and halts the action.
 */
final class FieldError
{
    /**
     * @param  list<string>  $fields  state names of the fields in the action's form
     */
    public static function report(InvalidCharge|InvalidCustomerService|InvalidQuote $exception, Action $action, array $fields): void
    {
        $field = self::fieldFor($exception->reason);

        if ($field !== null && in_array($field, $fields, true)) {
            throw ValidationException::withMessages([
                "mountedActions.{$action->getNestingIndex()}.data.{$field}" => $exception->getMessage(),
            ]);
        }

        Notification::make()
            ->danger()
            ->title($exception->getMessage())
            ->send();

        $action->halt();
    }

    public static function fieldFor(ChargeErrorReason|CustomerServiceErrorReason|QuoteErrorReason $reason): ?string
    {
        return match ($reason) {
            ChargeErrorReason::AmountNotPositive,
            ChargeErrorReason::HasAllocations,
            ChargeErrorReason::InvalidSplitAmount => 'amount',
            ChargeErrorReason::DescriptionRequired => 'description',
            ChargeErrorReason::CancelReasonRequired => 'reason',
            ChargeErrorReason::CustomerServiceOfAnotherCustomer => 'customer_service_id',
            ChargeErrorReason::NotOpen,
            ChargeErrorReason::CannotCancelWithAllocations => null,

            CustomerServiceErrorReason::PriceNotPositive => 'price',
            CustomerServiceErrorReason::EndDateNotAfterStartDate,
            CustomerServiceErrorReason::EndDateNotInFuture,
            CustomerServiceErrorReason::UpcomingChargeExists => 'end_date',
            CustomerServiceErrorReason::PeriodAlreadyEnded,
            CustomerServiceErrorReason::StartDateRequired,
            CustomerServiceErrorReason::StartDateBeforePreviousPeriodEnd => 'start_date',
            CustomerServiceErrorReason::ItemNotInSoldService,
            CustomerServiceErrorReason::CustomerArchived,
            CustomerServiceErrorReason::CancelledServiceLocked => null,

            QuoteErrorReason::RecipientRequired => 'recipient_name',
            QuoteErrorReason::RecipientEmailInvalid,
            QuoteErrorReason::NoRecipient => 'recipient_email',
            QuoteErrorReason::ItemRequired => 'items',
            QuoteErrorReason::ShortNameTaken => 'short_name',
            QuoteErrorReason::NotDraft,
            QuoteErrorReason::PriceNotPositive,
            QuoteErrorReason::InvalidItem,
            QuoteErrorReason::CustomerArchived,
            QuoteErrorReason::NotSendable,
            QuoteErrorReason::NotSent,
            QuoteErrorReason::Expired => null,
        };
    }
}
