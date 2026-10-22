<?php

namespace App\Exceptions;

use App\Enums\QuoteErrorReason;
use DomainException;

class InvalidQuote extends DomainException
{
    public function __construct(string $message, public readonly QuoteErrorReason $reason)
    {
        parent::__construct($message);
    }

    public static function recipientRequired(): self
    {
        return new self(__('quotes.recipient_required'), QuoteErrorReason::RecipientRequired);
    }

    public static function recipientEmailInvalid(): self
    {
        return new self(__('quotes.recipient_email_invalid'), QuoteErrorReason::RecipientEmailInvalid);
    }

    public static function notDraft(): self
    {
        return new self(__('quotes.not_draft'), QuoteErrorReason::NotDraft);
    }

    public static function priceNotPositive(): self
    {
        return new self(__('quotes.price_not_positive'), QuoteErrorReason::PriceNotPositive);
    }

    public static function itemRequired(): self
    {
        return new self(__('quotes.item_required'), QuoteErrorReason::ItemRequired);
    }

    public static function invalidItem(): self
    {
        return new self(__('quotes.invalid_item'), QuoteErrorReason::InvalidItem);
    }

    public static function customerArchived(): self
    {
        return new self(__('quotes.customer_archived'), QuoteErrorReason::CustomerArchived);
    }
}
