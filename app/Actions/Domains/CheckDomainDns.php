<?php

namespace App\Actions\Domains;

use App\Enums\DnsRecordType;
use App\Support\Dns\DnsLookup;
use App\Support\Dns\HostName;

/**
 * Asks DNS for every record type of a domain, live and without saving anything (Faz 16, "Alan adı kontrol penceresi").
 * An entry is null when that type could not be read and an empty list when the domain has no such record.
 */
class CheckDomainDns
{
    public function __construct(private readonly DnsLookup $dns) {}

    /**
     * @return array<string, list<string>|null> the display rows per record type value; null when the host is not valid
     */
    public function handle(string $domain): ?array
    {
        $host = HostName::normalize($domain);

        if ($host === null) {
            return null;
        }

        $records = [];

        foreach (DnsRecordType::cases() as $type) {
            $records[$type->value] = $this->dns->records($host, $type);
        }

        return $records;
    }
}
