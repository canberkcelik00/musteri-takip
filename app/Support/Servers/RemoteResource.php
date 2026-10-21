<?php

namespace App\Support\Servers;

/**
 * One metered resource of a hosting account (UAPI `ResourceUsage::get_usages`): disk, bandwidth
 * or a count such as email accounts. `limit` is null when the account has no limit. Byte
 * resources are kept in bytes, counts as plain numbers.
 */
final readonly class RemoteResource
{
    /**
     * Resource ids that cPanel reports in bytes; every other resource is a count.
     */
    private const array BYTE_IDS = ['disk_usage', 'bandwidth', 'cachedmysqldiskusage'];

    public function __construct(
        public string $id,
        public string $label,
        public int $used,
        public ?int $limit,
        public bool $isBytes,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromUapi(array $row): self
    {
        $id = (string) $row['id'];

        return new self(
            id: $id,
            label: (string) ($row['description'] ?? $id),
            used: self::toInt($row['usage'] ?? 0),
            limit: self::toLimit($row['maximum'] ?? null),
            isBytes: in_array($id, self::BYTE_IDS, true),
        );
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function toLimit(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
