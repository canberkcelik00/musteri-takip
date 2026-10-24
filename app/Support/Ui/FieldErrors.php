<?php

namespace App\Support\Ui;

use App\Enums\ChargeErrorReason;
use App\Enums\CustomerServiceErrorReason;
use App\Enums\QuoteErrorReason;

/**
 * Which form field a business-rule refusal belongs to (docs/UI.md §2 Kural 4). The domain exceptions only carry a
 * neutral reason code; the field is a UI concern and lives here. A reason with no field stays a window-level message.
 */
final class FieldErrors
{
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
