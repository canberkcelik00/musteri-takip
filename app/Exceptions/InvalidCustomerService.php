<?php

namespace App\Exceptions;

use App\Enums\CustomerServiceErrorReason;
use DomainException;

class InvalidCustomerService extends DomainException
{
    public function __construct(string $message, public readonly CustomerServiceErrorReason $reason)
    {
        parent::__construct($message);
    }

    public static function priceNotPositive(): self
    {
        return new self(__('customer_services.price_not_positive'), CustomerServiceErrorReason::PriceNotPositive);
    }

    public static function itemNotInSoldService(): self
    {
        return new self(__('customer_services.item_not_in_sold_service'), CustomerServiceErrorReason::ItemNotInSoldService);
    }

    public static function periodAlreadyEnded(): self
    {
        return new self(__('customer_services.period_already_ended'), CustomerServiceErrorReason::PeriodAlreadyEnded);
    }

    public static function customerArchived(): self
    {
        return new self(__('customer_services.customer_archived'), CustomerServiceErrorReason::CustomerArchived);
    }

    public static function startDateRequired(): self
    {
        return new self(__('customer_services.start_date_required'), CustomerServiceErrorReason::StartDateRequired);
    }

    public static function startDateBeforePreviousPeriodEnd(): self
    {
        return new self(__('customer_services.start_date_before_previous_period_end'), CustomerServiceErrorReason::StartDateBeforePreviousPeriodEnd);
    }

    public static function endDateNotAfterStartDate(): self
    {
        return new self(__('customer_services.end_date_not_after_start_date'), CustomerServiceErrorReason::EndDateNotAfterStartDate);
    }

    public static function endDateNotInFuture(): self
    {
        return new self(__('customer_services.end_date_not_in_future'), CustomerServiceErrorReason::EndDateNotInFuture);
    }

    public static function upcomingChargeExists(): self
    {
        return new self(__('customer_services.upcoming_charge_exists'), CustomerServiceErrorReason::UpcomingChargeExists);
    }

    public static function cancelledServiceLocked(): self
    {
        return new self(__('customer_services.cancelled_service_locked'), CustomerServiceErrorReason::CancelledServiceLocked);
    }
}
