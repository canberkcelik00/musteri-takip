<?php

namespace App\Actions\Quotes;

use App\Exceptions\InvalidQuote;
use App\Models\Customer;

class NormalizeQuoteRecipient
{
    private const RECIPIENT_FIELDS = ['recipient_name', 'recipient_company', 'recipient_phone', 'recipient_email'];

    /**
     * The recipient columns of a quote: an existing customer clears the hand-written recipient,
     * otherwise a name and a valid e-mail are required. An archived customer is rejected.
     *
     * @param  array<string, mixed>  $attributes  `recipient_*` keys; blank values count as none
     * @return array{customer_id: ?int, recipient_name: ?string, recipient_company: ?string, recipient_phone: ?string, recipient_email: ?string}
     *
     * @throws InvalidQuote
     */
    public function handle(?Customer $customer, array $attributes): array
    {
        if ($customer !== null) {
            if ($customer->isArchived()) {
                throw InvalidQuote::customerArchived();
            }

            return [
                'customer_id' => $customer->id,
                'recipient_name' => null,
                'recipient_company' => null,
                'recipient_phone' => null,
                'recipient_email' => null,
            ];
        }

        $recipient = [];

        foreach (self::RECIPIENT_FIELDS as $field) {
            $value = $attributes[$field] ?? null;
            $recipient[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if ($recipient['recipient_name'] === null || $recipient['recipient_email'] === null) {
            throw InvalidQuote::recipientRequired();
        }

        if (filter_var($recipient['recipient_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidQuote::recipientEmailInvalid();
        }

        return ['customer_id' => null, ...$recipient];
    }
}
