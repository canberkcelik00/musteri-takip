<?php

namespace App\Actions\Domains;

use App\Enums\DnsRecordType;
use App\Enums\EmailCheckStatus;
use App\Support\Dns\DnsLookup;
use App\Support\Dns\HostName;

/**
 * Reads the SPF, DKIM and DMARC records of a domain, live and without saving anything (Faz 16, "E-posta" tab).
 * Every entry says whether the record is fine, missing, wrong or could not be read, and for the last two which
 * problem it is (`reason` is the key of the Turkish fix sentence in `domain_check.mail.<record>.<reason>`).
 * DKIM is looked up under the cPanel default selector only: another selector may exist, which the sentence says.
 */
class CheckDomainEmail
{
    public const DKIM_SELECTOR = 'default';

    public function __construct(private readonly DnsLookup $dns) {}

    /**
     * @return array<string, array{status: EmailCheckStatus, record: string|null, reason: string|null}>|null keyed spf, dkim, dmarc; null when the host is not valid
     */
    public function handle(string $domain): ?array
    {
        $host = HostName::normalize($domain);

        if ($host === null) {
            return null;
        }

        return [
            'spf' => $this->spf($host),
            'dkim' => $this->dkim($host),
            'dmarc' => $this->dmarc($host),
        ];
    }

    /**
     * @return array{status: EmailCheckStatus, record: string|null, reason: string|null}
     */
    private function spf(string $host): array
    {
        $found = $this->txtStarting($host, 'v=spf1');

        if ($found === null) {
            return $this->result(EmailCheckStatus::Unreadable);
        }

        if ($found === []) {
            return $this->result(EmailCheckStatus::Missing, reason: 'missing');
        }

        if (count($found) > 1) {
            return $this->result(EmailCheckStatus::Invalid, $found[0], 'multiple');
        }

        $terms = preg_split('/\s+/', mb_strtolower($found[0])) ?: [];

        if (in_array('+all', $terms, true) || in_array('all', $terms, true)) {
            return $this->result(EmailCheckStatus::Invalid, $found[0], 'open');
        }

        $closed = collect($terms)->contains(fn (string $term): bool => in_array($term, ['-all', '~all', '?all'], true) || str_starts_with($term, 'redirect='));

        return $closed
            ? $this->result(EmailCheckStatus::Ok, $found[0])
            : $this->result(EmailCheckStatus::Invalid, $found[0], 'no_all');
    }

    /**
     * @return array{status: EmailCheckStatus, record: string|null, reason: string|null}
     */
    private function dkim(string $host): array
    {
        $name = self::DKIM_SELECTOR.'._domainkey.'.$host;
        $records = $this->dns->records($name, DnsRecordType::Txt);

        if ($records === null) {
            return $this->result(EmailCheckStatus::Unreadable);
        }

        $found = array_values(array_filter($records, fn (string $record): bool => (bool) preg_match('/(^|;)\s*(v=dkim1|p=)/i', $record)));

        if ($found === []) {
            return $this->result(EmailCheckStatus::Missing, reason: 'missing');
        }

        if (! preg_match('/(^|;)\s*p=\s*[^;\s]/i', $found[0])) {
            return $this->result(EmailCheckStatus::Invalid, $found[0], 'empty_key');
        }

        return $this->result(EmailCheckStatus::Ok, $found[0]);
    }

    /**
     * @return array{status: EmailCheckStatus, record: string|null, reason: string|null}
     */
    private function dmarc(string $host): array
    {
        $found = $this->txtStarting('_dmarc.'.$host, 'v=dmarc1');

        if ($found === null) {
            return $this->result(EmailCheckStatus::Unreadable);
        }

        if ($found === []) {
            return $this->result(EmailCheckStatus::Missing, reason: 'missing');
        }

        if (count($found) > 1) {
            return $this->result(EmailCheckStatus::Invalid, $found[0], 'multiple');
        }

        if (! preg_match('/;\s*p=\s*([a-z]+)/i', $found[0], $policy)) {
            return $this->result(EmailCheckStatus::Invalid, $found[0], 'no_policy');
        }

        return match (mb_strtolower($policy[1])) {
            'none' => $this->result(EmailCheckStatus::Ok, $found[0], 'monitoring'),
            'quarantine', 'reject' => $this->result(EmailCheckStatus::Ok, $found[0]),
            default => $this->result(EmailCheckStatus::Invalid, $found[0], 'bad_policy'),
        };
    }

    /**
     * The TXT records of a name that start with the given tag; null when the lookup failed.
     *
     * @return list<string>|null
     */
    private function txtStarting(string $name, string $tag): ?array
    {
        $records = $this->dns->records($name, DnsRecordType::Txt);

        if ($records === null) {
            return null;
        }

        return array_values(array_filter($records, fn (string $record): bool => str_starts_with(mb_strtolower(trim($record)), $tag)));
    }

    /**
     * @return array{status: EmailCheckStatus, record: string|null, reason: string|null}
     */
    private function result(EmailCheckStatus $status, ?string $record = null, ?string $reason = null): array
    {
        return ['status' => $status, 'record' => $record, 'reason' => $reason];
    }
}
