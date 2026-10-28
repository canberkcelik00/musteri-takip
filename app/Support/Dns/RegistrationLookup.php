<?php

namespace App\Support\Dns;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads when a domain's registration ends and who holds it (Faz 16): RDAP over HTTPS for most
 * extensions, the TRABİS WHOIS server (port 43) for `.tr`, which has no RDAP. Tests replace it
 * with a fake in the container.
 */
class RegistrationLookup
{
    private const BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    private const BOOTSTRAP_KEY = 'rdap.bootstrap';

    private const TR_WHOIS_SERVER = 'whois.trabis.gov.tr';

    /**
     * The registration; null when it could not be read (invalid host, an extension without a
     * public service, an unregistered name, timeout, or the network functions are disabled).
     */
    public function lookup(string $domain): ?Registration
    {
        $domain = HostName::normalize($domain);

        if ($domain === null) {
            return null;
        }

        $tld = substr($domain, (int) strrpos($domain, '.') + 1);

        if ($tld === 'tr') {
            $answer = $this->queryWhois(self::TR_WHOIS_SERVER, $domain);

            return $answer === null ? null : $this->parseTrWhois($answer);
        }

        $base = $this->rdapBaseFor($tld);

        if ($base === null) {
            return null;
        }

        try {
            $response = Http::timeout(8)->connectTimeout(5)->acceptJson()->get("{$base}domain/{$domain}");
        } catch (Throwable) {
            return null;
        }

        $data = $response->successful() ? $response->json() : null;

        return is_array($data) ? $this->parseRdap($data) : null;
    }

    /**
     * @param  array<string, mixed>  $data  an RDAP domain object
     */
    public function parseRdap(array $data): ?Registration
    {
        $expiresOn = null;

        foreach ((array) ($data['events'] ?? []) as $event) {
            if (is_array($event) && ($event['eventAction'] ?? null) === 'expiration' && is_string($event['eventDate'] ?? null)) {
                $expiresOn = $this->dayOf(substr($event['eventDate'], 0, 10), 'Y-m-d');
            }
        }

        $registrar = null;

        foreach ((array) ($data['entities'] ?? []) as $entity) {
            if (is_array($entity) && in_array('registrar', (array) ($entity['roles'] ?? []), true)) {
                $registrar = $this->vcardName($entity['vcardArray'] ?? null);

                break;
            }
        }

        $nameservers = [];

        foreach ((array) ($data['nameservers'] ?? []) as $nameserver) {
            if (is_array($nameserver) && is_string($nameserver['ldhName'] ?? null)) {
                $nameservers[] = $this->nameserverName($nameserver['ldhName']);
            }
        }

        return $expiresOn === null && $registrar === null ? null : new Registration($expiresOn, $registrar, $this->cleanNameservers($nameservers));
    }

    /**
     * @param  string  $text  the TRABİS WHOIS answer for a domain
     */
    public function parseTrWhois(string $text): ?Registration
    {
        $expiresOn = null;

        if (preg_match('/^Expires on\.*:\s*(\d{4}-[A-Za-z]{3}-\d{2})/m', $text, $match) === 1) {
            $expiresOn = $this->dayOf($match[1], 'Y-M-d');
        }

        $registrar = null;

        if (preg_match('/^\*\* Registrar:\s*\R(?:.*\R)*?Organization Name\s*:\s*(.+)$/m', $text, $match) === 1) {
            $registrar = trim($match[1]) ?: null;
        }

        $nameservers = [];

        if (preg_match('/^\*\* Domain Servers:\s*\R((?:.+\R?)*)/m', $text, $match) === 1) {
            foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
                $nameservers[] = $this->nameserverName(strtok(trim($line), " \t") ?: '');
            }
        }

        return $expiresOn === null && $registrar === null ? null : new Registration($expiresOn, $registrar, $this->cleanNameservers($nameservers));
    }

    /**
     * One WHOIS query over port 43; null when the server cannot be reached or says nothing.
     */
    protected function queryWhois(string $server, string $query): ?string
    {
        if (! function_exists('stream_socket_client')) {
            return null;
        }

        $client = @stream_socket_client("tcp://{$server}:43", $errorCode, $errorMessage, 5);

        if ($client === false) {
            return null;
        }

        stream_set_timeout($client, 8);
        fwrite($client, "{$query}\r\n");
        $answer = stream_get_contents($client, 65536);
        fclose($client);

        return is_string($answer) && trim($answer) !== '' ? $answer : null;
    }

    /**
     * The RDAP service address (ending in a slash) of an extension, from the IANA list that is kept
     * for a week; null when the extension has none or the list cannot be read.
     */
    protected function rdapBaseFor(string $tld): ?string
    {
        $bootstrap = Cache::get(self::BOOTSTRAP_KEY);

        if (! is_array($bootstrap)) {
            try {
                $response = Http::timeout(8)->connectTimeout(5)->acceptJson()->get(self::BOOTSTRAP_URL);
            } catch (Throwable) {
                return null;
            }

            $bootstrap = $response->successful() ? $response->json() : null;

            if (! is_array($bootstrap)) {
                return null;
            }

            Cache::put(self::BOOTSTRAP_KEY, $bootstrap, now()->addWeek());
        }

        foreach ((array) ($bootstrap['services'] ?? []) as $service) {
            if (! is_array($service) || ! in_array($tld, (array) ($service[0] ?? []), true)) {
                continue;
            }

            $urls = array_values(array_filter((array) ($service[1] ?? []), 'is_string'));
            $secure = array_values(array_filter($urls, fn (string $url): bool => str_starts_with($url, 'https://')));
            $url = $secure[0] ?? $urls[0] ?? null;

            return $url === null ? null : rtrim($url, '/').'/';
        }

        return null;
    }

    private function dayOf(string $date, string $format): ?CarbonImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!'.$format, $date);

        return $day === false ? null : CarbonImmutable::instance($day);
    }

    private function nameserverName(string $name): string
    {
        return mb_strtolower(rtrim(trim($name), '.'));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function cleanNameservers(array $names): array
    {
        return array_values(array_unique(array_filter($names, fn (string $name): bool => $name !== '')));
    }

    private function vcardName(mixed $vcard): ?string
    {
        foreach ((array) (is_array($vcard) ? ($vcard[1] ?? []) : []) as $property) {
            if (is_array($property) && ($property[0] ?? null) === 'fn' && is_string($property[3] ?? null) && $property[3] !== '') {
                return $property[3];
            }
        }

        return null;
    }
}
