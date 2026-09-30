<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * A small colored badge for a table column's description (T-70): used to show a service's or
 * charge's status under its name/description below `md`, where the desktop status column is
 * hidden (docs/UI.md, G-4.16). Deliberately NOT a {@see Stack}
 * or `Split`: using either anywhere in a table's `columns()` switches the whole table to
 * Filament's "content layout" rendering, which has no `<thead>` — confirmed from source
 * (`Filament\Tables\Table\Concerns\HasColumns::$hasColumnsLayout` and the classic-table branch
 * in `index.blade.php` being gated on `! $hasColumnsLayout`) and by reproducing the regression
 * (column headers and row actions disappeared at every width, not just below `md`). A plain
 * `TextColumn::description()` accepts `Htmlable`, so the badge is rendered as real markup
 * (matching `<x-filament::badge>`) inside that description instead, wrapped in the `fi-mobile-badge`
 * class (`resources/css/filament/panel/theme.css`, `@apply md:hidden ...`) so it only shows below
 * `md`; the column itself, and the table's classic rendering, stay intact. A raw `md:hidden`
 * class here (T-70) was never actually built (T-71): Tailwind's content scanner does not emit
 * variant-prefixed candidates found only in PHP-built strings, so a named, `@apply`-backed class
 * is used instead (the same pattern Filament itself uses for its own responsive classes).
 */
class MobileStatusBadge
{
    public static function html(string $label, string $color): Htmlable
    {
        return new HtmlString(View::make('filament.support.mobile-status-badge', [
            'label' => $label,
            'color' => $color,
        ])->render());
    }
}
