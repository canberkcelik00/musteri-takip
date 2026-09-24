<?php

namespace App\Exceptions;

use DomainException;

class InvalidPaymentAllocation extends DomainException
{
    public static function amountNotPositive(): self
    {
        return new self(__('payments.amount_not_positive'));
    }

    public static function chargeOfAnotherCustomer(): self
    {
        return new self(__('payments.charge_of_another_customer'));
    }

    public static function currencyMismatch(): self
    {
        return new self(__('payments.currency_mismatch'));
    }

    public static function chargeNotOpen(): self
    {
        return new self(__('payments.charge_not_open'));
    }

    public static function exceedsChargeRemaining(): self
    {
        return new self(__('payments.exceeds_charge_remaining'));
    }

    public static function exceedsPaymentAmount(): self
    {
        return new self(__('payments.exceeds_payment_amount'));
    }
}
