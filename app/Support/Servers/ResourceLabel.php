<?php

namespace App\Support\Servers;

use Illuminate\Support\Facades\Lang;

/**
 * The Turkish name of a hosting resource (lang/tr/usage.php). A resource the lang file does not
 * know keeps the description the server gave, so an unexpected resource id still reads.
 */
final class ResourceLabel
{
    public static function for(string $id, string $fallback): string
    {
        return Lang::has("usage.resource.{$id}") ? __("usage.resource.{$id}") : $fallback;
    }
}
