<?php

namespace App\Exceptions;

use DomainException;

class InvalidCustomerService extends DomainException
{
    public static function priceNotPositive(): self
    {
        return new self(__('customer_services.price_not_positive'));
    }

    public static function itemNotInSoldService(): self
    {
        return new self(__('customer_services.item_not_in_sold_service'));
    }

    public static function periodAlreadyEnded(): self
    {
        return new self(__('customer_services.period_already_ended'));
    }

    public static function customerArchived(): self
    {
        return new self(__('customer_services.customer_archived'));
    }

    public static function startDateRequired(): self
    {
        return new self(__('customer_services.start_date_required'));
    }

    public static function startDateBeforePreviousPeriodEnd(): self
    {
        return new self(__('customer_services.start_date_before_previous_period_end'));
    }

    public static function endDateNotAfterStartDate(): self
    {
        return new self(__('customer_services.end_date_not_after_start_date'));
    }

    public static function endDateNotInFuture(): self
    {
        return new self(__('customer_services.end_date_not_in_future'));
    }

    public static function upcomingChargeExists(): self
    {
        return new self(__('customer_services.upcoming_charge_exists'));
    }

    public static function cancelledServiceLocked(): self
    {
        return new self(__('customer_services.cancelled_service_locked'));
    }
}
