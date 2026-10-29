<?php

namespace App\Enums;

/**
 * What a Claude connection may do (U-43): only read, or read and use the few write tools. Chosen per connection in the
 * profile; every connection starts as read only.
 */
enum McpAccess: string
{
    case ReadOnly = 'read';
    case ReadWrite = 'read_write';

    public function getLabel(): string
    {
        return __('enums.mcp_access.'.$this->value);
    }
}
