<?php

namespace App\Mcp\Tools;

use App\Enums\McpAccess;
use Laravel\Mcp\Request;

/**
 * A tool that changes data (U-42): only a "read and write" token sees and runs it, and its input goes into the log
 * line. It calls the same Action as the panel, with the token owner as the actor. There is never a delete, cancel,
 * archive or undo tool.
 */
abstract class WriteTool extends BaseTool
{
    protected function ability(): string
    {
        return McpAccess::WRITE_ABILITY;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loggedArguments(Request $request): array
    {
        return ['arguments' => $request->all()];
    }
}
