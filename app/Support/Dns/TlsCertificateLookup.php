<?php

namespace App\Support\Dns;

use Carbon\CarbonImmutable;

/**
 * Reads the certificate a host presents on port 443 (Faz 16). The chain is not verified on purpose:
 * an expired or self-signed certificate is exactly what the check has to report, not a failure.
 * Tests replace it with a fake in the container.
 */
class TlsCertificateLookup
{
    /**
     * The certificate; null when none could be read (invalid host, no HTTPS, timeout, or the
     * functions are disabled).
     */
    public function certificateOf(string $host, int $timeoutSeconds = 5): ?TlsCertificate
    {
        $host = HostName::normalize($host);

        if ($host === null || ! function_exists('stream_socket_client') || ! function_exists('openssl_x509_parse')) {
            return null;
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client("ssl://{$host}:443", $errorCode, $errorMessage, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return null;
        }

        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);

        $parsed = $certificate === null ? false : @openssl_x509_parse($certificate);

        return is_array($parsed) ? $this->fromParsed($parsed) : null;
    }

    /**
     * @param  array<string, mixed>  $parsed  the array `openssl_x509_parse` returns
     */
    public function fromParsed(array $parsed): ?TlsCertificate
    {
        $validTo = $parsed['validTo_time_t'] ?? null;

        if (! is_int($validTo)) {
            return null;
        }

        return new TlsCertificate(
            CarbonImmutable::createFromTimestamp($validTo),
            $this->nameOf($parsed['issuer'] ?? null, ['O', 'CN']),
            $this->nameOf($parsed['subject'] ?? null, ['CN']),
        );
    }

    /**
     * @param  list<string>  $keys  the distinguished-name parts to try, in order
     */
    private function nameOf(mixed $name, array $keys): ?string
    {
        if (! is_array($name)) {
            return null;
        }

        foreach ($keys as $key) {
            $value = $name[$key] ?? null;
            $value = is_array($value) ? ($value[0] ?? null) : $value;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
