<?php

namespace App\Support;

/**
 * The initials and color of a customer's round avatar (G-4C.7, docs/UI.md §6). Purely a display
 * helper: the same customer gets the same letters and color on every screen. The letters are
 * upper-cased the Turkish way (i → İ, ı → I), and the color comes from the id, so neighbouring
 * customers differ.
 */
final class Avatar
{
    /** How many avatar colors the theme defines (`--mt-avatar-1` … `--mt-avatar-8`). */
    public const int COLORS = 8;

    /**
     * Two letters: the first letter of the first two words, or the first two letters of a
     * one-word name ("Logi" → "LO").
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '?';
        }

        $letters = count($words) > 1
            ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)
            : mb_substr($words[0], 0, 2);

        return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $letters));
    }

    /**
     * The 1-based color number of a customer, stable for the same id.
     */
    public static function colorNumber(int $id): int
    {
        return $id % self::COLORS + 1;
    }

    /**
     * The attributes that draw the avatar beside a table cell's text: the theme paints the circle
     * with a pseudo-element from `data-initials` (see `.fi-mt-avatar-cell`).
     *
     * @return array{class: string, 'data-initials': string}
     */
    public static function cellAttributes(string $name, int $id): array
    {
        return [
            'class' => 'fi-mt-avatar-cell fi-mt-avatar-'.self::colorNumber($id),
            'data-initials' => self::initials($name),
        ];
    }
}
