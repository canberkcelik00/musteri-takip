<?php

namespace App\Enums;

/**
 * The DNS record types the domain tools ask for (Faz 16).
 */
enum DnsRecordType: string
{
    case A = 'a';
    case Aaaa = 'aaaa';
    case Mx = 'mx';
    case Ns = 'ns';
    case Txt = 'txt';
    case Cname = 'cname';

    /**
     * The `dns_get_record` type constant.
     */
    public function constant(): int
    {
        return match ($this) {
            self::A => DNS_A,
            self::Aaaa => DNS_AAAA,
            self::Mx => DNS_MX,
            self::Ns => DNS_NS,
            self::Txt => DNS_TXT,
            self::Cname => DNS_CNAME,
        };
    }
}
