<?php

use App\Mcp\Servers\MusteriTakipServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Middleware\CheckToken;

// Claude access (U-43): a client finds the sign-in through the discovery routes and registers itself; the dynamic
// registration is open to anyone, so it is rate limited (and only the redirect addresses in config/mcp.php are taken).
Route::middleware('throttle:mcp-oauth')->group(function (): void {
    Mcp::oauthRoutes();
});

// Only an OAuth access token with the MCP scope gets in; the route is outside the web group, so there is no session
// or cookie.
Mcp::web('/mcp', MusteriTakipServer::class)
    ->middleware(['auth:api', CheckToken::class.':'.Registrar::OAUTH_SCOPE, 'active', 'throttle:mcp'])
    ->name('mcp');
