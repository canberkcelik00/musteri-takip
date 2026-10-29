<?php

/*
 * The Claude access endpoint (U-43). Only the keys changed from the laravel/mcp defaults are listed here.
 */
return [

    /*
     * Where a connecting client may be sent back to after the owner approved it (checked when the client registers
     * itself): Claude on the web, desktop and phone, and the loopback address Claude Code listens on. Any other
     * address, such as `https://evil.example`, is refused, so an approval can never be handed to a stranger.
     */
    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'http://localhost',
    ],

    'custom_schemes' => [],

];
