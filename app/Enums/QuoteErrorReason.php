<?php

namespace App\Enums;

enum QuoteErrorReason: string
{
    case RecipientRequired = 'recipient_required';
    case RecipientEmailInvalid = 'recipient_email_invalid';
    case NotDraft = 'not_draft';
    case PriceNotPositive = 'price_not_positive';
    case ItemRequired = 'item_required';
    case InvalidItem = 'invalid_item';
    case CustomerArchived = 'customer_archived';
    case NotSendable = 'not_sendable';
    case NoRecipient = 'no_recipient';
    case NotSent = 'not_sent';
    case Expired = 'expired';
    case ShortNameTaken = 'short_name_taken';
}
