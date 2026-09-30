<?php

namespace App\Enums;

enum CustomerServiceErrorReason: string
{
    case PriceNotPositive = 'price_not_positive';
    case ItemNotInSoldService = 'item_not_in_sold_service';
    case PeriodAlreadyEnded = 'period_already_ended';
    case CustomerArchived = 'customer_archived';
    case StartDateRequired = 'start_date_required';
    case StartDateBeforePreviousPeriodEnd = 'start_date_before_previous_period_end';
    case EndDateNotAfterStartDate = 'end_date_not_after_start_date';
    case EndDateNotInFuture = 'end_date_not_in_future';
    case UpcomingChargeExists = 'upcoming_charge_exists';
    case CancelledServiceLocked = 'cancelled_service_locked';
}
