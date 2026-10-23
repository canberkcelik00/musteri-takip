<?php

namespace App\Support\AvatarProviders;

use App\Support\Avatar;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The user menu's avatar as in iOS Contacts: the two initials in white on a soft gray gradient,
 * drawn as an inline SVG, so no request leaves for an avatar service (docs/UI.md §6, T-133).
 * An image cannot read the theme's CSS variables, so its two grays are fixed here.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public const GRADIENT_TOP = '#a5a5aa';

    public const GRADIENT_BOTTOM = '#86868b';

    public function get(Model|Authenticatable $record): string
    {
        $initials = e(Avatar::initials(Filament::getNameForDefaultAvatar($record)));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0" stop-color="'.self::GRADIENT_TOP.'"/>'
            .'<stop offset="1" stop-color="'.self::GRADIENT_BOTTOM.'"/></linearGradient></defs>'
            .'<rect width="64" height="64" fill="url(#g)"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#fff" font-size="26" font-weight="600" '
            .'font-family="-apple-system, BlinkMacSystemFont, sans-serif">'.$initials.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
