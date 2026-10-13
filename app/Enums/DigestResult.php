<?php

namespace App\Enums;

/**
 * What a scheduler run of SendDigest did.
 */
enum DigestResult: string
{
    /** The digest was sent. */
    case Sent = 'sent';

    /** The period was due but every section was empty; the period is closed without an e-mail. */
    case Empty = 'empty';

    /** The digest of the last scheduled moment was already handled. */
    case NotDue = 'not_due';

    /** No notification address is set; nothing was recorded. */
    case NoRecipient = 'no_recipient';

    /** The first run: only the starting moment was recorded, the first digest goes at the next scheduled moment. */
    case Started = 'started';
}
