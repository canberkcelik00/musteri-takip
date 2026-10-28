<?php

namespace App\Enums;

/**
 * What a Claude access token may do (U-42): only read, or read and use the few write tools. Stored on the token as
 * Sanctum abilities, so a tool checks `tokenCan()` with one of the ability constants.
 */
enum McpAccess: string
{
    public const READ_ABILITY = 'mcp:read';

    public const WRITE_ABILITY = 'mcp:write';

    case ReadOnly = 'read';
    case ReadWrite = 'read_write';

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::ReadOnly => [self::READ_ABILITY],
            self::ReadWrite => [self::READ_ABILITY, self::WRITE_ABILITY],
        };
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public static function fromAbilities(array $abilities): self
    {
        return in_array(self::WRITE_ABILITY, $abilities, true) ? self::ReadWrite : self::ReadOnly;
    }

    public function getLabel(): string
    {
        return __('enums.mcp_access.'.$this->value);
    }
}
