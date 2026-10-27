<?php

namespace App\Support\Dns;

/**
 * Reads the NS records of a host name from DNS (T-169). Tests replace it with a fake in the
 * container, so no test asks a real name server.
 */
class NameserverLookup
{
    /**
     * The name servers of the host, lower case. An empty list when the host has no NS record of
     * its own; null when the lookup itself failed (no answer, or DNS functions are disabled).
     *
     * @return list<string>|null
     */
    public function nameserversOf(string $host): ?array
    {
        if (! function_exists('dns_get_record')) {
            return null;
        }

        $records = @dns_get_record($host, DNS_NS);

        if ($records === false) {
            return null;
        }

        return array_map(
            fn (array $record): string => mb_strtolower(rtrim((string) ($record['target'] ?? ''), '.')),
            $records,
        );
    }
}
