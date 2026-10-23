<?php

namespace App\Actions\Reminders;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use App\Support\Money;
use App\Support\Settings;

/**
 * The `wa.me` link that opens a WhatsApp chat with the customer's primary contact and a ready
 * message about one open charge (G-5B.4). Nothing is sent or recorded: the owner reads the message
 * and presses send in WhatsApp. There is no link when the charge is not open, the customer is
 * archived or the primary contact has no phone number that can be turned into an international one.
 */
class BuildWhatsAppReminder
{
    private const string BASE_URL = 'https://wa.me/';

    /** @var array{name: ?string, iban: ?string}|null */
    private ?array $company = null;

    public function __construct(private readonly Settings $settings) {}

    /**
     * The phone number in the international form WhatsApp links need (digits only, country code
     * first): "0111 111 11 11", "(111) 111-1111" and "+90 111 111 11 11" all give "901111111111".
     * A number with a "+" or "00" is taken as having its country code (8 to 15 digits); a Turkish
     * number is a 10-digit one, with or without the leading 0 or the 90. Anything else is null.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        $digits = (string) preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($phone, '+') || str_starts_with($digits, '00')) {
            $international = str_starts_with($phone, '+') ? $digits : substr($digits, 2);

            return preg_match('/^[1-9]\d{7,14}$/', $international) === 1 ? $international : null;
        }

        $national = match (true) {
            strlen($digits) === 10 => $digits,
            strlen($digits) === 11 && $digits[0] === '0' => substr($digits, 1),
            strlen($digits) === 12 && str_starts_with($digits, '90') => substr($digits, 2),
            default => null,
        };

        return $national === null ? null : '90'.$national;
    }

    /**
     * The link for the charge, or null when none can be offered.
     */
    public function handle(Charge $charge): ?string
    {
        $customer = $charge->customer;
        $contact = $customer->primaryContact;

        if ($charge->status !== ChargeStatus::Open || $customer->isArchived() || $contact === null) {
            return null;
        }

        $phone = self::normalizePhone($contact->phone);

        if ($phone === null) {
            return null;
        }

        return self::BASE_URL.$phone.'?text='.rawurlencode($this->message($charge, $customer, $contact->name));
    }

    /**
     * The company name and IBAN, read once per instance so a page of rows does not read the
     * settings again for every row.
     *
     * @return array{name: ?string, iban: ?string}
     */
    private function company(): array
    {
        return $this->company ??= array_intersect_key($this->settings->companyDetails(), ['name' => 1, 'iban' => 1]);
    }

    private function message(Charge $charge, Customer $customer, string $contactName): string
    {
        $company = $this->company();
        $domain = $charge->customerService?->domains()[0] ?? null;

        $message = __($charge->isOverdue() ? 'reminders.whatsapp.overdue' : 'reminders.whatsapp.due', [
            'name' => $contactName,
            'description' => ($domain !== null ? $domain.' – ' : '').$charge->description,
            'amount' => (new Money($charge->remainingAmount(), $charge->currency))->format(),
            'date' => $charge->due_date->format('d.m.Y'),
        ]);

        if ($company['iban'] !== null) {
            $message .= ' '.__($company['name'] !== null ? 'reminders.whatsapp.iban_company' : 'reminders.whatsapp.iban', [
                'company' => $company['name'],
                'iban' => trim(chunk_split($company['iban'], 4, ' ')),
            ]);
        }

        return $message.' '.__('reminders.whatsapp.closing').($company['name'] !== null ? ' '.$company['name'] : '');
    }
}
