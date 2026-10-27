<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An https address on pcloud.com or pcloud.link (subdomains included). The host is compared as a
 * whole-label suffix, so "evilpcloud.com" and "pcloud.com.evil.io" are rejected.
 */
class PcloudUrl implements ValidationRule
{
    /** @var list<string> */
    private const HOSTS = ['pcloud.com', 'pcloud.link'];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('customers.pcloud_url_invalid')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        $parts = parse_url($value);

        if ($parts === false || mb_strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));

        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
