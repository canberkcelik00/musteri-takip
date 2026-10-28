<?php

namespace App\Support\Dns;

use App\Enums\DnsRecordType;

/**
 * Asks DNS for one record type of a host (Faz 16). Nothing is stored. Tests replace it with a fake
 * in the container, so no test asks a real name server.
 */
class DnsLookup
{
    /**
     * The records as display strings (an MX as "10 mail.example.com", a TXT as its text). An empty
     * list when the host has none; null when the lookup itself failed (invalid host, no answer, or
     * DNS functions are disabled).
     *
     * @return list<string>|null
     */
    public function records(string $host, DnsRecordType $type): ?array
    {
        $host = HostName::normalize($host);

        if ($host === null || ! function_exists('dns_get_record')) {
            return null;
        }

        $records = @dns_get_record($host, $type->constant());

        if ($records === false) {
            return null;
        }

        return $this->format($type, $records);
    }

    /**
     * @param  list<array<string, mixed>>  $records  the rows `dns_get_record` returns
     * @return list<string>
     */
    public function format(DnsRecordType $type, array $records): array
    {
        if ($type === DnsRecordType::Mx) {
            usort($records, fn (array $a, array $b): int => (int) ($a['pri'] ?? 0) <=> (int) ($b['pri'] ?? 0));
        }

        $values = [];

        foreach ($records as $record) {
            $value = match ($type) {
                DnsRecordType::A => (string) ($record['ip'] ?? ''),
                DnsRecordType::Aaaa => mb_strtolower((string) ($record['ipv6'] ?? '')),
                DnsRecordType::Ns, DnsRecordType::Cname => mb_strtolower(rtrim((string) ($record['target'] ?? ''), '.')),
                DnsRecordType::Mx => trim(((int) ($record['pri'] ?? 0)).' '.mb_strtolower(rtrim((string) ($record['target'] ?? ''), '.'))),
                DnsRecordType::Txt => (string) ($record['txt'] ?? implode('', (array) ($record['entries'] ?? []))),
            };

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
