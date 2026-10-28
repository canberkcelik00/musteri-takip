<?php

namespace App\Actions\Domains;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerServiceItem;
use App\Support\Dns\HostName;
use App\Support\Dns\TlsCertificateLookup;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Reads the certificate of a domain live and without saving anything (the SSL tab of "Alan adı kontrol
 * penceresi"). When the live read fails, the expiry the SSL check stored on the domain's services is shown
 * instead and flagged as stored; issuer and subject are only ever read live.
 */
class CheckDomainSsl
{
    public function __construct(private readonly TlsCertificateLookup $lookup) {}

    /**
     * @return array{expires_on: string, days_left: int, issuer: string|null, subject: string|null, stored: bool}|null
     *                                                                                                                 null when the host is not valid or no certificate could be read and none is stored; days_left is negative once expired
     */
    public function handle(string $domain): ?array
    {
        $host = HostName::normalize($domain);

        if ($host === null) {
            return null;
        }

        $certificate = $this->lookup->certificateOf($host);

        if ($certificate !== null) {
            return $this->answer($certificate->expiresAt->setTimezone(config('app.timezone')), $certificate->issuer, $certificate->subject, false);
        }

        $stored = CustomerServiceItem::query()
            ->whereNotNull('ssl_expires_at')
            ->whereHas('customerService', fn ($services) => $services->where('status', CustomerServiceStatus::Active))
            ->get()
            ->filter(fn (CustomerServiceItem $item): bool => HostName::normalize((string) ($item->details['domain'] ?? '')) === $host)
            ->sortByDesc('ssl_checked_at')
            ->first();

        return $stored === null ? null : $this->answer($stored->ssl_expires_at, null, null, true);
    }

    /**
     * @return array{expires_on: string, days_left: int, issuer: string|null, subject: string|null, stored: bool}
     */
    private function answer(CarbonInterface $expiresAt, ?string $issuer, ?string $subject, bool $stored): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $end = Carbon::parse($expiresAt->toDateString(), config('app.timezone'))->startOfDay();

        return [
            'expires_on' => $end->toDateString(),
            'days_left' => (int) $today->diffInDays($end, false),
            'issuer' => $issuer,
            'subject' => $subject,
            'stored' => $stored,
        ];
    }
}
