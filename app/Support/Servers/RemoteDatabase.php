<?php

namespace App\Support\Servers;

/**
 * One MySQL database of a hosting account and the disk it takes, in bytes.
 */
final readonly class RemoteDatabase
{
    public function __construct(public string $name, public int $bytes) {}

    /**
     * Reads UAPI `Mysql::list_databases`; `disk_usage` may arrive as a numeric string.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromUapi(array $row): self
    {
        return new self(
            name: (string) $row['database'],
            bytes: is_numeric($row['disk_usage'] ?? null) ? (int) $row['disk_usage'] : 0,
        );
    }
}
