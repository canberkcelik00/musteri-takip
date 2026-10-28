<?php

use App\Enums\McpAccess;
use App\Mcp\Servers\MusteriTakipServer;
use Laravel\Mcp\Facades\Mcp;

// Claude access (U-42): only a personal access token gets in; the route is outside the web group, so there is no
// session or cookie.
Mcp::web('/mcp', MusteriTakipServer::class)
    ->middleware(['auth:sanctum', 'abilities:'.McpAccess::READ_ABILITY, 'active', 'throttle:mcp'])
    ->name('mcp');
