<?php

namespace App\Actions\Domains;

use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Models\CustomerServiceItem;
use App\Support\Dns\HostName;
use App\Support\Dns\RegistrationLookup;
use Illuminate\Support\Carbon;

/**
 * Asks WHOIS/RDAP for the registration of a domain, live and without saving anything (G-16.4, the WHOIS tab of
 * "Alan adı kontrol penceresi"), and compares its end with the end of the customer's domain services. When the live
 * read fails the last stored answer of the WHOIS check is shown instead and flagged as stored.
 */
class CheckDomainRegistration
{
    /** A registration end this many days or more away from the service end is reported. */
    public const int MISMATCH_DAYS = 7;

    public function __construct(private readonly RegistrationLookup $lookup) {}

    /**
     * @return array{expires_on: string|null, registrar: string|null, stored: bool, mismatches: list<array{customer: string, service_end: string, days: int}>}|null
     *                                                                                                                                                              null when the host is not valid; expires_on and registrar are both null when nothing could be read
     */
    public function handle(string $domain): ?array
    {
        $host = HostName::normalize($domain);

        if ($host === null) {
            return null;
        }

        $items = CustomerServiceItem::query()
            ->where('type', ServiceType::Domain)
            ->whereHas('customerService', fn ($services) => $services->where('status', CustomerServiceStatus::Active))
            ->with('customerService.customer')
            ->get()
            ->filter(fn (CustomerServiceItem $item): bool => HostName::normalize((string) ($item->details['domain'] ?? '')) === $host);

        $registration = $this->lookup->lookup($host);
        $stored = false;
        $expiresOn = $registration?->expiresOn?->toDateString();
        $registrar = $registration?->registrar;

        if ($registration === null) {
            $known = $items->filter(fn (CustomerServiceItem $item): bool => $item->registration_expires_at !== null || $item->registrar !== null)->first();

            if ($known !== null) {
                $stored = true;
                $expiresOn = $known->registration_expires_at?->toDateString();
                $registrar = $known->registrar;
            }
        }

        return [
            'expires_on' => $expiresOn,
            'registrar' => $registrar,
            'stored' => $stored,
            'mismatches' => $expiresOn === null ? [] : $this->mismatches($items->all(), $expiresOn),
        ];
    }

    /**
     * @param  list<CustomerServiceItem>  $items
     * @return list<array{customer: string, service_end: string, days: int}>
     */
    private function mismatches(array $items, string $expiresOn): array
    {
        $registrationEnd = Carbon::parse($expiresOn, config('app.timezone'))->startOfDay();
        $mismatches = [];

        foreach ($items as $item) {
            $serviceEnd = Carbon::parse($item->customerService->end_date->toDateString(), config('app.timezone'))->startOfDay();
            $days = (int) abs($serviceEnd->diffInDays($registrationEnd));

            if ($days > self::MISMATCH_DAYS) {
                $mismatches[] = [
                    'customer' => $item->customerService->customer->short_name,
                    'service_end' => $serviceEnd->toDateString(),
                    'days' => $days,
                ];
            }
        }

        return $mismatches;
    }
}
