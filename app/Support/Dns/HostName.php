<?php

namespace App\Support\Dns;

/**
 * Cleans up and checks a host name before it is sent to DNS, a socket or an HTTP client (Faz 16).
 */
class HostName
{
    /**
     * The host in lower case without a trailing dot, or null when it is not a plausible domain name
     * (a scheme, a path, a port, an address, a single label or a label that is too long).
     */
    public static function normalize(string $host): ?string
    {
        $host = rtrim(mb_strtolower(trim($host)), '.');

        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $host = $ascii === false ? '' : $ascii;
        }

        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

        if (preg_match("/^(?:{$label}\\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/", $host) !== 1) {
            return null;
        }

        return $host;
    }
}
