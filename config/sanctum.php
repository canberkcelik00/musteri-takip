<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
 * Sanctum guards only the Claude access endpoint (/mcp, U-42) and only with tokens: no domain is stateful and no
 * session guard is tried first, so a signed-in browser never gets in. Each token carries its own expiry (90 days,
 * see CreateMcpToken), so there is no global expiration.
 */
return [

    'stateful' => [],

    'guard' => [],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
